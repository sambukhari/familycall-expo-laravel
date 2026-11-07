<?php

namespace Tests\Feature;

use App\Models\CameraDevice;
use App\Models\CameraOwnerKey;
use App\Models\CameraViewSession;
use App\Models\FamilyAccount;
use App\Models\FamilyCall;
use App\Models\User;
use App\Services\CameraTotp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CameraAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private FamilyAccount $tv;

    private string $viewer = 'test-browser-camera-grant';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'services.family_admin_email' => 'owner@example.test',
            'camera.turn_urls' => ['turn:relay.example.test:3478'], 'camera.turn_secret' => str_repeat('test', 10)]);
        $this->owner = User::factory()->create(['email' => 'owner@example.test', 'password' => 'camera-test-password']);
        $this->tv = FamilyAccount::create(['username' => 'tv-camera-test', 'password' => 'device-test-password', 'device_type' => 'tv']);
    }

    private function unlock(?User $owner = null): void
    {
        $owner ??= $this->owner;
        $key = CameraOwnerKey::firstOrCreate(['user_id' => $owner->id], [
            'secret' => app(CameraTotp::class)->generateSecret(), 'confirmed_at' => now(),
        ]);
        $this->actingAs($owner)->withSession(['camera_owner_id' => $owner->id, 'camera_key_id' => $key->id,
            'camera_access_until' => now()->addMinutes(30)->timestamp, 'camera_viewer_id' => $this->viewer]);
    }

    private function device(?string $token = null): CameraDevice
    {
        return CameraDevice::create(['owner_id' => $this->owner->id, 'family_account_id' => $this->tv->id,
            'label' => 'Room', 'paired_at' => now(), 'token_hash' => hash('sha256', $token ?? Str::random(64)),
            'armed' => true, 'last_seen_at' => now(), 'camera_available' => true]);
    }

    private function startView(CameraDevice $device): string
    {
        return $this->postJson('/admin/cameras/'.$device->id.'/sessions', [
            'offer' => ['type' => 'offer', 'sdp' => 'v=0'],
        ])->assertCreated()->json('session.id');
    }

    public function test_admin_password_and_fresh_authenticator_code_are_required(): void
    {
        $this->getJson('/admin/cameras')->assertUnauthorized();
        $this->actingAs($this->owner)->postJson('/admin/cameras', [])->assertStatus(423);
        $this->postJson('/admin/cameras/security/enroll', ['password' => 'wrong'])->assertStatus(422);
        $secret = $this->postJson('/admin/cameras/security/enroll', ['password' => 'camera-test-password'])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('secret');
        $this->assertNotSame($secret, DB::table('camera_owner_keys')->first()->secret);
        $code = app(CameraTotp::class)->code($secret, intdiv(now()->timestamp, 30));
        $this->postJson('/admin/cameras/security/unlock', ['password' => 'camera-test-password', 'code' => $code])
            ->assertOk()->assertJsonPath('unlocked', true)->assertJsonStructure(['csrf_token']);
        $this->getJson('/admin/cameras/ice')->assertOk();
        $this->postJson('/admin/cameras/security/unlock', ['password' => 'camera-test-password', 'code' => $code])->assertStatus(422);
        $this->travel(31)->minutes();
        $this->getJson('/admin/cameras/ice')->assertStatus(423);
    }

    public function test_pairing_code_is_one_use_hashed_and_expires(): void
    {
        $this->unlock();
        $pair = $this->postJson('/admin/cameras', ['label' => 'Room', 'family_account_id' => $this->tv->id])->assertCreated();
        $device = CameraDevice::findOrFail($pair->json('device_id'));
        $this->assertNotSame($pair->json('pair_code'), $device->pair_code_hash);
        $token = $this->postJson('/api/camera/pair', ['code' => $pair->json('pair_code')])->assertCreated()->json('token');
        $this->assertSame(hash('sha256', $token ?? Str::random(64)), $device->fresh()->token_hash);
        $this->postJson('/api/camera/pair', ['code' => $pair->json('pair_code')])->assertStatus(422);
        $next = $this->postJson('/admin/cameras', ['label' => 'Expired', 'family_account_id' => $this->tv->id])->assertCreated();
        $this->travel(11)->minutes();
        $this->postJson('/api/camera/pair', ['code' => $next->json('pair_code')])->assertStatus(422);
    }

    public function test_unpaired_disarmed_offline_and_busy_devices_cannot_be_viewed(): void
    {
        $this->unlock();
        $device = $this->device();
        $payload = ['offer' => ['type' => 'offer', 'sdp' => 'v=0']];
        foreach ([['armed' => false], ['armed' => true, 'last_seen_at' => now()->subMinute()],
            ['last_seen_at' => now(), 'camera_available' => false], ['camera_available' => true, 'paired_at' => null]] as $change) {
            $device->update($change);
            $this->postJson('/admin/cameras/'.$device->id.'/sessions', $payload)->assertStatus(409);
        }
        $device->update(['paired_at' => now()]);
        FamilyCall::create(['caller_id' => 'account:'.$this->tv->id, 'callee_id' => 'account:900', 'status' => 'ringing', 'offer' => ['type' => 'offer', 'sdp' => 'v=0']]);
        $this->postJson('/admin/cameras/'.$device->id.'/sessions', $payload)->assertStatus(409);
    }

    public function test_viewer_and_device_identity_are_both_enforced(): void
    {
        $this->unlock();
        $device = $this->device(str_repeat('a', 64));
        $id = $this->startView($device);
        $this->getJson('/admin/cameras/sessions/'.$id)->assertOk();
        $this->withSession(['camera_viewer_id' => 'another-browser'])->getJson('/admin/cameras/sessions/'.$id)->assertNotFound();
        $this->device(str_repeat('b', 64));
        $this->withToken(str_repeat('b', 64))->postJson('/api/camera/sessions/'.$id.'/answer', ['answer' => ['type' => 'answer', 'sdp' => 'v=0']])->assertNotFound();
        $this->withToken('invalid')->postJson('/api/camera/poll', [])->assertUnauthorized();
        $other = User::factory()->create(['email' => 'different@example.test']);
        config(['services.family_admin_email' => $other->email]);
        $this->unlock($other);
        $this->postJson('/admin/cameras/'.$device->id.'/revoke', [])->assertNotFound();
        $this->getJson('/admin/cameras')->assertJsonCount(0, 'devices');
    }

    public function test_answer_ice_and_end_flow_does_not_store_media(): void
    {
        $this->unlock();
        $token = str_repeat('a', 64);
        $device = $this->device($token);
        $id = $this->startView($device);
        $poll = ['armed' => true, 'camera_available' => true, 'state' => 'ready'];
        $this->withToken($token)->postJson('/api/camera/poll', $poll)->assertOk()->assertJsonPath('session.id', $id)
            ->assertJsonStructure(['session' => ['lease_seconds', 'offer', 'ice_servers']]);
        $this->postJson('/api/camera/sessions/'.$id.'/answer', ['answer' => ['type' => 'answer', 'sdp' => 'v=0']])->assertOk();
        $candidate = ['candidates' => [['nonce' => (string) Str::uuid(), 'payload' => ['candidate' => 'candidate:example', 'sdpMid' => '0', 'sdpMLineIndex' => 0]]]];
        $this->postJson('/api/camera/sessions/'.$id.'/signals', $candidate)->assertOk();
        $this->postJson('/api/camera/sessions/'.$id.'/signals', $candidate)->assertOk();
        $this->getJson('/admin/cameras/sessions/'.$id)->assertJsonCount(1, 'signals')->assertJsonPath('session.answer.type', 'answer');
        $this->postJson('/admin/cameras/sessions/'.$id.'/end', [])->assertOk();
        $this->assertNull(CameraViewSession::findOrFail($id)->offer);
        $this->assertNull(CameraViewSession::findOrFail($id)->answer);
        $this->assertDatabaseCount('camera_signals', 0);
        $this->postJson('/api/camera/poll', $poll)->assertJsonPath('session', null);
    }

    public function test_revocation_immediately_invalidates_credentials_and_ends_stream(): void
    {
        $this->unlock();
        $token = str_repeat('c', 64);
        $device = $this->device($token);
        $id = $this->startView($device);
        $this->postJson('/admin/cameras/'.$device->id.'/revoke', [])->assertNoContent();
        $this->withToken($token)->postJson('/api/camera/poll', ['armed' => true, 'camera_available' => true, 'state' => 'ready'])->assertUnauthorized();
        $this->assertSame('revoked', CameraViewSession::findOrFail($id)->end_reason);
    }

    public function test_expired_viewer_lease_cannot_be_revived_by_a_late_heartbeat(): void
    {
        $this->unlock();
        $device = $this->device();
        $id = $this->startView($device);
        $this->travel(31)->seconds();
        $device->update(['last_seen_at' => now()]);
        $this->postJson('/admin/cameras/sessions/'.$id.'/heartbeat', [])->assertStatus(409);
        $this->assertSame('viewer_disconnected', CameraViewSession::findOrFail($id)->end_reason);
        $this->assertNull(CameraViewSession::findOrFail($id)->offer);
    }

    public function test_family_call_releases_camera_on_next_agent_poll(): void
    {
        $this->unlock();
        $token = str_repeat('d', 64);
        $id = $this->startView($this->device($token));
        FamilyCall::create(['caller_id' => 'account:999', 'callee_id' => 'account:'.$this->tv->id, 'status' => 'ringing', 'offer' => ['type' => 'offer', 'sdp' => 'v=0']]);
        $this->withToken($token)->postJson('/api/camera/poll', ['armed' => true, 'camera_available' => true, 'state' => 'viewing'])
            ->assertJsonPath('session', null)->assertJsonPath('busy', true);
        $this->assertSame('family_call', CameraViewSession::findOrFail($id)->end_reason);
    }

    public function test_recovery_invalidates_old_unlocks_and_cleanup_removes_stale_sdp(): void
    {
        $this->unlock();
        $id = $this->startView($this->device());
        $this->artisan('camera:reset-authenticator', ['email' => $this->owner->email, '--force' => true])->assertSuccessful();
        $this->getJson('/admin/cameras/ice')->assertStatus(423);
        $this->assertSame('authenticator_reset', CameraViewSession::findOrFail($id)->end_reason);
        $this->unlock();
        $id = $this->startView($this->device());
        $this->travel(1)->minutes();
        $this->artisan('camera:prune')->assertSuccessful();
        $this->assertNull(CameraViewSession::findOrFail($id)->offer);
    }

    public function test_lock_ends_the_current_browser_session_and_requires_unlock_again(): void
    {
        $this->unlock();
        $id = $this->startView($this->device());
        $this->postJson('/admin/cameras/security/lock', [])->assertOk();
        $this->getJson('/admin/cameras/ice')->assertStatus(423);
        $this->assertSame('owner_locked', CameraViewSession::findOrFail($id)->end_reason);
    }

    public function test_signaling_preserves_the_required_sdp_line_endings(): void
    {
        $this->unlock();
        $token = str_repeat('e', 64);
        $device = $this->device($token);
        $sdp = "v=0\r\na=fmtp:49 repair-window=10000000\r\n";
        $id = $this->postJson('/admin/cameras/'.$device->id.'/sessions', ['offer' => ['type' => 'offer', 'sdp' => $sdp]])
            ->assertCreated()->json('session.id');
        $this->assertSame($sdp, CameraViewSession::findOrFail($id)->offer['sdp']);
        $this->withToken($token)->postJson('/api/camera/sessions/'.$id.'/answer', ['answer' => ['type' => 'answer', 'sdp' => $sdp]])->assertOk();
        $this->assertSame($sdp, CameraViewSession::findOrFail($id)->answer['sdp']);
    }

    public function test_turn_is_required_and_credentials_are_short_lived(): void
    {
        $this->unlock();
        $server = $this->getJson('/admin/cameras/ice')->assertOk()->json('ice_servers.0');
        $this->assertSame(now()->timestamp + 1200, (int) explode(':', $server['username'])[0]);
        $this->assertSame(base64_encode(hash_hmac('sha1', $server['username'], config('camera.turn_secret'), true)), $server['credential']);
        config(['camera.turn_secret' => '']);
        $this->getJson('/admin/cameras/ice')->assertStatus(503);
    }
}
