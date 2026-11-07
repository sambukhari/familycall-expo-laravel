<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FamilyAdminEvent;
use App\Models\FamilyCall;
use App\Models\FamilyDevice;
use App\Models\FamilyDeviceCommand;
use App\Models\FamilyDeviceToken;
use App\Models\FamilyMessage;
use App\Services\FamilyPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DashboardController extends Controller {
 private function audit(string $action,?string $device=null,array $details=[]):void {
  FamilyAdminEvent::create(['actor'=>(string)auth()->user()->email,'action'=>$action,'device_id'=>$device,'details'=>$details]);
 }
 private function devices() {
  return FamilyDevice::orderBy('device_id')->get()->map(fn($d)=>[
   'id'=>$d->id,'device_id'=>$d->device_id,'label'=>$d->label,'authorized'=>$d->authorized,
   'last_seen_at'=>$d->last_seen_at?->toIso8601String(),
   'online'=>$d->last_seen_at && $d->last_seen_at->gt(now()->subSeconds(35)),
   'platform'=>$d->platform,'tokens'=>FamilyDeviceToken::where('device_id',$d->device_id)->count(),
  ]);
 }
 public function index(Request $r) {
  $tab=(string)$r->query('tab','overview');
  if(!in_array($tab,['overview','devices','families','calls','messages','logs','cameras'],true))$tab='overview';
  $devices=$this->devices();
  $calls=FamilyCall::query()->orderByDesc('id')->limit(100)->get(['id','caller_id','callee_id','status','created_at','updated_at']);
  $messages=FamilyMessage::orderByDesc('id')->limit(150)->get();
  $events=FamilyAdminEvent::orderByDesc('id')->limit(150)->get();
  $commands=FamilyDeviceCommand::orderByDesc('id')->limit(70)->get();
  return Inertia::render('Dashboard',[
   'tab'=>$tab,'viewer'=>auth()->user()->name,'serverTime'=>now()->toIso8601String(),
   'summary'=>['devices'=>$devices->count(),'online'=>$devices->where('online',true)->count(),'blocked'=>$devices->where('authorized',false)->count(),'calls'=>FamilyCall::count(),'activeCalls'=>FamilyCall::whereIn('status',['ringing','connected'])->count(),'messages'=>FamilyMessage::count()],
   'families'=>\App\Models\Family::orderBy('name')->get(['id','name']),
   'accounts'=>\App\Models\FamilyAccount::orderBy('username')->get(['id','username','device_type','family_id','active']),
   'devices'=>$devices,'calls'=>$calls,'messages'=>$messages,'events'=>$events,'commands'=>$commands,
   'flash'=>session('notice'),
  ]);
 }
 public function authorizeDevice(Request $r,FamilyDevice $device) {
  $data=$r->validate(['authorized'=>'required|boolean']);
  $device->update(['authorized'=>(bool)$data['authorized']]);
  $this->audit($device->authorized?'device.authorized':'device.blocked',$device->device_id);
  return back()->with('notice',$device->device_id.' is '.($device->authorized?'authorized':'blocked'));
 }
 public function rename(Request $r,FamilyDevice $device) {
  $data=$r->validate(['label'=>'required|string|max:120']);
  $device->update(['label'=>$data['label']]);
  $this->audit('device.renamed',$device->device_id);
  return back()->with('notice','Device label updated');
 }
 public function ping(FamilyDevice $device){
  abort_unless($device->authorized,422,'Blocked device');
  $command=FamilyDeviceCommand::create(['device_id'=>$device->device_id,'type'=>'ping']);
  $this->audit('device.ping.queued',$device->device_id,['command_id'=>$command->id]);
  return back()->with('notice','Ping queued. Requires client command polling for acknowledgement.');
 }
 public function notification(Request $r,FamilyDevice $device,FamilyPush $push){
  abort_unless($device->authorized,422,'Blocked device');
  $data=$r->validate(['title'=>'required|string|max:90','body'=>'required|string|max:180']);
  $result=$push->test($device->device_id,$data['title'],$data['body']);
  $this->audit('device.notification.test',$device->device_id,['result'=>$result]);
  return back()->with('notice',$result['message']);
 }
 public function sendMessage(Request $r){
  $data=$r->validate(['from'=>'required|string|max:80','to'=>'required|string|max:80','body'=>'required|string|max:4000']);
  abort_if($data['from']===$data['to'],422,'Choose different devices');
  foreach([$data['from'],$data['to']] as $id)abort_unless(FamilyDevice::where('device_id',$id)->where('authorized',true)->exists(),422,'Device unavailable: '.$id);
  $message=FamilyMessage::create(['sender_id'=>$data['from'],'recipient_id'=>$data['to'],'body'=>$data['body']]);
  FamilyDeviceCommand::create(['device_id'=>$data['to'],'type'=>'new_message','payload'=>['message_id'=>$message->id]]);
  $this->audit('message.admin.created',$data['to'],['message_id'=>$message->id,'from'=>$data['from']]);
  return back()->with('notice','Message saved and device command queued. Recipient must poll messages to display it.');
 }
}

