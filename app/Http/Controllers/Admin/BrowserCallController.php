<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FamilyAccount;
use App\Models\FamilyCall;
use App\Services\FamilyPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrowserCallController extends Controller
{
    private function identity(Request $request): string { return 'admin:'.$request->user()->id; }
    private function check(Request $request, FamilyCall $call): void
    {
        abort_unless($call->caller_id === $this->identity($request), 403);
    }
    public function start(Request $request, FamilyPush $push)
    {
        $data = $request->validate(['to'=>['required','integer'], 'offer'=>['required','array'],
            'offer.type'=>['required','in:offer'], 'offer.sdp'=>['required','string','max:100000']]);
        $admin = $this->identity($request);
        $call = DB::transaction(function () use ($data, $admin) {
            $account = FamilyAccount::whereKey($data['to'])->lockForUpdate()->firstOrFail();
            abort_unless($account->active && $account->family_id, 422, 'Member is unavailable or unassigned');
            $target = 'account:'.$account->id;
            $busy = FamilyCall::whereIn('status',['ringing','connected'])
                ->where(fn($q)=>$q->whereIn('caller_id',[$admin,$target])->orWhereIn('callee_id',[$admin,$target]))->exists();
            abort_if($busy,409,'A participant is already in a call');
            return FamilyCall::create(['caller_id'=>$admin,'callee_id'=>$target,'status'=>'ringing','offer'=>$data['offer']]);
        });
        try { $push->incoming($call->callee_id, 'Administrator', $call->id); }
        catch (\Throwable $e) { \Log::warning('Admin call notification failed', ['call_id'=>$call->id]); }
        return response()->json(['call'=>$call],201);
    }
    public function show(Request $request, FamilyCall $call)
    {
        $this->check($request,$call);
        return ['call'=>$call,'signals'=>$call->signals()->where('id','>',max(0,(int)$request->query('after',0)))->orderBy('id')->limit(200)->get()];
    }
    public function signal(Request $request, FamilyCall $call)
    {
        $this->check($request,$call);
        abort_if($call->status === 'ended',409);
        $data=$request->validate(['kind'=>['required','in:ice'],'payload'=>['required','array']]);
        return response()->json(['signal'=>$call->signals()->create(['sender_id'=>$this->identity($request),'kind'=>$data['kind'],'payload'=>$data['payload']])],201);
    }
    public function end(Request $request, FamilyCall $call)
    {
        $this->check($request,$call);
        $call->update(['status'=>'ended']);
        return ['call'=>$call->fresh()];
    }
}
