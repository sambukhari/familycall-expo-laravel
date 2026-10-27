<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\FamilyCall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CallController extends Controller {
 private function device(Request $r): string {return (string)$r->header('X-Device-ID');}
 private function authorized(Request $r,FamilyCall $c): void {
  abort_unless(in_array($this->device($r),[$c->caller_id,$c->callee_id],true),403);
 }
 public function active(Request $r) {
  $id=$this->device($r);
  $call=FamilyCall::whereIn('status',['ringing','connected'])
   ->where(fn($q)=>$q->where('caller_id',$id)->orWhere('callee_id',$id))->latest('id')->first();
  return ['call'=>$call];
 }
 public function start(Request $r) {
  $data=$r->validate(['to'=>'required|string|max:80','offer'=>'required|array','offer.type'=>'required|in:offer','offer.sdp'=>'required|string']);
  $from=$this->device($r);abort_if($from===$data['to'],422,'Cannot call yourself');
  return DB::transaction(function()use($data,$from){
   $ids=[$from,$data['to']];
   $busy=FamilyCall::whereIn('status',['ringing','connected'])
    ->where(fn($q)=>$q->whereIn('caller_id',$ids)->orWhereIn('callee_id',$ids))->lockForUpdate()->exists();
   abort_if($busy,409,'Device already in a call');
   $call=FamilyCall::create(['caller_id'=>$from,'callee_id'=>$data['to'],'status'=>'ringing','offer'=>$data['offer']]);
   return response()->json(['call'=>$call],201);
  });
 }
 public function answer(Request $r,FamilyCall $call) {
  $this->authorized($r,$call);abort_unless($this->device($r)===$call->callee_id,403);
  abort_unless($call->status==='ringing',409);
  $data=$r->validate(['answer'=>'required|array','answer.type'=>'required|in:answer','answer.sdp'=>'required|string']);
  $call->update(['answer'=>$data['answer'],'status'=>'connected']);
  return ['call'=>$call->fresh()];
 }
 public function end(Request $r,FamilyCall $call) {
  $this->authorized($r,$call);$call->update(['status'=>'ended']);return ['call'=>$call->fresh()];
 }
 public function signals(Request $r,FamilyCall $call) {
  $this->authorized($r,$call);
  return ['signals'=>$call->signals()->where('id','>',max(0,(int)$r->query('after',0)))->orderBy('id')->limit(200)->get()];
 }
 public function signal(Request $r,FamilyCall $call) {
  $this->authorized($r,$call);abort_if($call->status==='ended',409);
  $data=$r->validate(['kind'=>'required|in:ice','payload'=>'required|array']);
  $signal=$call->signals()->create(['sender_id'=>$this->device($r),'kind'=>$data['kind'],'payload'=>$data['payload']]);
  return response()->json(['signal'=>$signal],201);
 }
}
