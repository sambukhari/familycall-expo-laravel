<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\FamilyDevice;
use App\Models\FamilyMessage;
use App\Models\FamilyDeviceCommand;
use Illuminate\Http\Request;
class DeviceControlController extends Controller {
 private function id(Request $r):string{return (string)$r->header('X-Device-ID');}
 public function heartbeat(Request $r){
  $d=FamilyDevice::where('device_id',$this->id($r))->firstOrFail();
  $d->update(['last_seen_at'=>now(),'platform'=>'android']);
  return ['ok'=>true,'server_time'=>now()->toIso8601String()];
 }
 public function commands(Request $r){
  return ['commands'=>FamilyDeviceCommand::where('device_id',$this->id($r))->whereNull('acknowledged_at')->orderBy('id')->limit(25)->get(['id','type','payload','created_at'])];
 }
 public function acknowledge(Request $r,FamilyDeviceCommand $command){
  abort_unless($command->device_id===$this->id($r),403);
  $command->update(['delivered_at'=>$command->delivered_at??now(),'acknowledged_at'=>now()]);
  return ['ok'=>true];
 }
 public function messages(Request $r){
  $id=$this->id($r);$after=max(0,(int)$r->query('after',0));
  return ['messages'=>FamilyMessage::where('id','>',$after)->where(fn($q)=>$q->where('sender_id',$id)->orWhere('recipient_id',$id))->orderBy('id')->limit(100)->get()];
 }
 public function sendMessage(Request $r){
  $data=$r->validate(['to'=>'required|string|max:80','body'=>'required|string|max:4000']);
  $id=$this->id($r);abort_if($id===$data['to'],422);
  abort_unless(FamilyDevice::where('device_id',$data['to'])->where('authorized',true)->exists(),403,'Recipient not authorized');
  $m=FamilyMessage::create(['sender_id'=>$id,'recipient_id'=>$data['to'],'body'=>$data['body']]);
  return response()->json(['message'=>$m],201);
 }
 public function markRead(Request $r,FamilyMessage $message){
  abort_unless($message->recipient_id===$this->id($r),403);
  $message->update(['read_at'=>now()]);
  return ['ok'=>true];
 }
}
