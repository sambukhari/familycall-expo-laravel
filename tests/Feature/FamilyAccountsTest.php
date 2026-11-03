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

    public function test_family_call_answer_and_ice_are_restricted_to_participants(): void
    {
        $family = Family::create(['name'=>'Family']);
        $caller = $this->postJson('/api/account/register',['username'=>'caller','password'=>'test-password-123','device_type'=>'phone'])->assertCreated();
        $callee = $this->postJson('/api/account/register',['username'=>'callee','password'=>'test-password-123','device_type'=>'tv'])->assertCreated();
        $outsider = $this->postJson('/api/account/register',['username'=>'outsider','password'=>'test-password-123','device_type'=>'phone'])->assertCreated();
        $a = FamilyAccount::where('username','caller')->firstOrFail();
        $b = FamilyAccount::where('username','callee')->firstOrFail();
        $a->update(['family_id'=>$family->id]);
        $b->update(['family_id'=>$family->id]);
        $created = $this->withToken($caller->json('token'))->postJson('/api/account/calls',[
            'to'=>$b->id,'offer'=>['type'=>'offer','sdp'=>'v=0'],
        ])->assertCreated();
        $id=$created->json('call.id');
        $this->withToken($outsider->json('token'))->getJson('/api/account/calls/'.$id.'/signals')->assertForbidden();
        $this->withToken($callee->json('token'))->postJson('/api/account/calls/'.$id.'/answer',[
            'answer'=>['type'=>'answer','sdp'=>'v=0'],
        ])->assertOk()->assertJsonPath('call.status','connected');
        $this->withToken($caller->json('token'))->postJson('/api/account/calls/'.$id.'/signals',[
            'kind'=>'ice','payload'=>['candidate'=>'candidate:1'],
        ])->assertCreated();
        $this->withToken($callee->json('token'))->getJson('/api/account/calls/'.$id.'/signals')->assertOk()->assertJsonCount(1,'signals');
        $this->withToken($callee->json('token'))->postJson('/api/account/calls/'.$id.'/end')->assertOk()->assertJsonPath('call.status','ended');
    }

    public function test_admin_can_call_a_member_but_another_session_cannot_read_it(): void
    {
        $admin = \App\Models\User::factory()->create(['email'=>'admin@example.com']);
        config(['services.family_admin_email'=>'admin@example.com']);
        $member = FamilyAccount::create(['username'=>'member','password'=>'test-password-123','device_type'=>'tv',
            'family_id'=>Family::create(['name'=>'Family'])->id]);
        $this->postJson('/admin/browser-calls',['to'=>$member->id,'offer'=>['type'=>'offer','sdp'=>'v=0']])->assertUnauthorized();
        $created=$this->actingAs($admin)->postJson('/admin/browser-calls',[
            'to'=>$member->id,'offer'=>['type'=>'offer','sdp'=>'v=0'],
        ])->assertCreated();
        $this->actingAs($admin)->getJson('/admin/browser-calls/'.$created->json('call.id'))->assertOk();
        $this->actingAs($admin)->postJson('/admin/browser-calls/'.$created->json('call.id').'/end')->assertOk();
    }
}
