<?php
namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_exchange_messages_but_other_families_cannot_read_them(): void
    {
        $a=$this->postJson('/api/account/register',['username'=>'alice','password'=>'password-alice','device_type'=>'phone'])->assertCreated();
        $b=$this->postJson('/api/account/register',['username'=>'brian','password'=>'password-brian','device_type'=>'tv'])->assertCreated();
        $c=$this->postJson('/api/account/register',['username'=>'charlie','password'=>'password-charlie','device_type'=>'phone'])->assertCreated();
        $family=Family::create(['name'=>'Home']);
        $alice=FamilyAccount::where('username','alice')->firstOrFail();
        $brian=FamilyAccount::where('username','brian')->firstOrFail();
        $charlie=FamilyAccount::where('username','charlie')->firstOrFail();
        $alice->update(['family_id'=>$family->id]);
        $brian->update(['family_id'=>$family->id]);
        $charlie->update(['family_id'=>Family::create(['name'=>'Other'])->id]);
        $this->withToken($a->json('token'))->postJson('/api/account/members/'.$brian->id.'/messages',['body'=>'Hello'])->assertCreated();
        $this->withToken($b->json('token'))->getJson('/api/account/members/'.$alice->id.'/messages')
            ->assertOk()->assertJsonPath('messages.0.body','Hello');
        $this->withToken($c->json('token'))->getJson('/api/account/members/'.$alice->id.'/messages')->assertForbidden();
        $this->withToken($c->json('token'))->postJson('/api/account/members/'.$alice->id.'/messages',['body'=>'No'])->assertForbidden();
        $this->withToken($b->json('token'))->postJson('/api/account/members/'.$alice->id.'/read')->assertOk();
        $this->withToken($a->json('token'))->getJson('/api/account/members/'.$brian->id.'/messages')->assertJsonPath('messages.0.read_at', \App\Models\FamilyMessage::firstOrFail()->read_at->toJSON());
    }
}
