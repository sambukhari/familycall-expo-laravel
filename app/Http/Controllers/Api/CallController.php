<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\FamilyCall;
use App\Models\CallSignal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\FamilyPush;
class CallController extends Controller {
 private function device(Request $r): string {return (string)$r->header('X-Device-ID');}
 private function authorizeCall(Request $r, FamilyCall $call): void {abort_unless(in_array($this->device($r),[$call->caller_id,$call->callee_id],true),403);}
 public function active(Request $r){$id=$this->device($r);$call=FamilyCall::whereIn('status',['ringing','connected'])->where(fn($q)=>$q->where('caller_id',$id)->orWhere('callee_id',$id))->latest('id')->first();return response()->json(['call'=>$call]);}
 public function start(Request $r){$data=$r->validate(['to'=>'required|string|max:80','offer'=>'required|array','offer.type'=>'required|in:offer','offer.sdp'=>'required|string']);$from=$this->device($r);abort_if($from===$data['to'],422,'Cannot call yourself');$response = DB::transaction(function()use($from,$data){$busy=FamilyCall::whereIn('status',['ringing','connected'])->where(fn($q)=>$q->whereIn('caller_id',[$from,$data['to']])->orWhereIn('callee_id',[$from,$data['to']]))->lockForUpdate()->exists();abort_if($busy,409,'A device is already in a call');$call=FamilyCall::create(['caller_id'=>$from,'callee_id'=>$data['to'],'status'=>'ringing','offer'=>$data['offer']]);return response()->json(['call'=>$call],201);});
  // Push after transaction commits; never delay a successful call because FCM is unavailable.
  try {app(FamilyPush::class)->incoming($data['to'],$from,$response->getData(true)['call']['id']);} catch (\Throwable $e) {\Log::warning('FamilyCall push failed', ['message'=>$e->getMessage()]);}
  return $response;
 }
 public function answer(Request $r,FamilyCall $call){$this->authorizeCall($r,$call);abort_unless($this->device($r)===$call->callee_id,403);abort_unless($call->status==='ringing',409);$data=$r->validate(['answer'=>'required|array','answer.type'=>'required|in:answer','answer.sdp'=>'required|string']);$call->update(['answer'=>$data['answer'],'status'=>'connected']);return ['call'=>$call->fresh()];}
 public function end(Request $r,FamilyCall $call){$this->authorizeCall($r,$call);$call->update(['status'=>'ended']);return ['call'=>$call->fresh()];}
 public function signals(Request $r,FamilyCall $call){$this->authorizeCall($r,$call);$after=max(0,(int)$r->query('after',0));return ['signals'=>$call->signals()->where('id','>',$after)->orderBy('id')->limit(200)->get()];}
 public function signal(Request $r,FamilyCall $call){$this->authorizeCall($r,$call);abort_if($call->status==='ended',409);$data=$r->validate(['kind'=>'required|in:ice','payload'=>'required|array']);$signal=$call->signals()->create(['sender_id'=>$this->device($r),'kind'=>$data['kind'],'payload'=>$data['payload']]);return response()->json(['signal'=>$signal],201);}
}
