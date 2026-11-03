<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_login_and_members_are_scoped_to_the_assigned_family(): void
    {
        $response = $this->postJson('/api/account/register', [
            'username' => 'alice', 'password' => 'test-password-123', 'device_type' => 'tv',
        ])->assertCreated();
        $token = $response->json('token');
        $account = FamilyAccount::where('username', 'alice')->firstOrFail();
        $family = Family::create(['name' => 'One family']);
        $account->update(['family_id' => $family->id]);
        FamilyAccount::create(['username'=>'bob','password'=>'test-password-123','device_type'=>'phone','family_id'=>$family->id]);
        FamilyAccount::create(['username'=>'outsider','password'=>'test-password-123','device_type'=>'phone','family_id'=>Family::create(['name'=>'Other'])->id]);

        $this->withToken($token)->getJson('/api/account/members')->assertOk()
            ->assertJsonCount(1, 'members')->assertJsonPath('members.0.username', 'bob');
        $this->postJson('/api/account/login', ['username'=>'alice','password'=>'incorrect'])->assertUnauthorized();
        $this->withToken($token)->postJson('/api/account/logout')->assertNoContent();
        $this->withToken($token)->getJson('/api/account/me')->assertUnauthorized();
    }

    public function test_call_to_another_family_is_forbidden(): void
    {
        $response = $this->postJson('/api/account/register', [
            'username'=>'alice','password'=>'test-password-123','device_type'=>'phone',
        ])->assertCreated();
        $alice = FamilyAccount::where('username','alice')->firstOrFail();
        $alice->update(['family_id'=>Family::create(['name'=>'First'])->id]);
        $other = FamilyAccount::create(['username'=>'other','password'=>'test-password-123',
            'device_type'=>'tv','family_id'=>Family::create(['name'=>'Second'])->id]);
        $this->withToken($response->json('token'))->postJson('/api/account/calls', [
            'to'=>$other->id,'offer'=>['type'=>'offer','sdp'=>'v=0'],
        ])->assertForbidden();
    }
}
