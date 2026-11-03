<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyAccount;
use App\Models\FamilyCall;
use App\Models\FamilyDeviceToken;
use App\Services\FamilyPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FamilyCallController extends Controller
{
    private function identity(Request $request): string
    {
        return 'account:'.$request->attributes->get('family_account')->id;
    }

    private function authorizeCall(Request $request, FamilyCall $call): void
    {
        abort_unless(in_array($this->identity($request), [$call->caller_id, $call->callee_id], true), 403);
    }

    public function active(Request $request)
    {
        $id = $this->identity($request);
        return ['call' => FamilyCall::whereIn('status', ['ringing','connected'])
            ->where(fn($q) => $q->where('caller_id', $id)->orWhere('callee_id', $id))->latest('id')->first()];
    }

    public function start(Request $request, FamilyPush $push)
    {
        $data = $request->validate(['to' => ['required','integer'], 'offer' => ['required','array'],
            'offer.type' => ['required','in:offer'], 'offer.sdp' => ['required','string','max:100000']]);
        $account = $request->attributes->get('family_account');
        abort_unless($account->family_id, 403, 'No family assigned');
        $target = FamilyAccount::whereKey($data['to'])->where('active', true)->firstOrFail();
        abort_unless($target->family_id === $account->family_id && $target->id !== $account->id, 403);
        $from = $this->identity($request);
        $to = 'account:'.$target->id;
        $call = DB::transaction(function () use ($account, $target, $from, $to, $data) {
            FamilyAccount::whereKey([$account->id, $target->id])->orderBy('id')->lockForUpdate()->get();
            $busy = FamilyCall::whereIn('status',['ringing','connected'])
                ->where(fn($q) => $q->whereIn('caller_id',[$from,$to])->orWhereIn('callee_id',[$from,$to]))->exists();
            abort_if($busy, 409, 'A member is already in a call');
            return FamilyCall::create(['caller_id'=>$from,'callee_id'=>$to,'status'=>'ringing','offer'=>$data['offer']]);
        });
        try { $push->incoming($to, $account->username, $call->id); } catch (\Throwable $e) {
            \Log::warning('Family call push failed', ['call_id'=>$call->id]);
        }
        return response()->json(['call'=>$call], 201);
    }

    public function answer(Request $request, FamilyCall $call)
    {
        $this->authorizeCall($request, $call);
        abort_unless($this->identity($request) === $call->callee_id, 403);
        $data = $request->validate(['answer' => ['required','array'], 'answer.type'=>['required','in:answer'],
            'answer.sdp'=>['required','string','max:100000']]);
        $updated = FamilyCall::whereKey($call->id)->where('status','ringing')->update(['answer'=>json_encode($data['answer']), 'status'=>'connected', 'updated_at'=>now()]);
        abort_unless($updated, 409, 'Call is no longer ringing');
        return ['call'=>$call->fresh()];
    }

    public function end(Request $request, FamilyCall $call)
    {
        $this->authorizeCall($request, $call);
        $call->update(['status'=>'ended']);
        return ['call'=>$call->fresh()];
    }

    public function signals(Request $request, FamilyCall $call)
    {
        $this->authorizeCall($request, $call);
        return ['signals'=>$call->signals()->where('id','>',max(0,(int)$request->query('after',0)))->orderBy('id')->limit(200)->get()];
    }

    public function signal(Request $request, FamilyCall $call)
    {
        $this->authorizeCall($request, $call);
        abort_if($call->status === 'ended', 409);
        $data = $request->validate(['kind'=>['required','in:ice'],'payload'=>['required','array']]);
        return response()->json(['signal'=>$call->signals()->create(['sender_id'=>$this->identity($request),'kind'=>$data['kind'],'payload'=>$data['payload']])],201);
    }

    public function pushToken(Request $request)
    {
        $data = $request->validate(['token'=>['required','string','max:255','regex:/^(ExponentPushToken|ExpoPushToken)\\[[^\\]]+\\]$/']]);
        FamilyDeviceToken::updateOrCreate(['token'=>$data['token']],['device_id'=>$this->identity($request),'platform'=>'android']);
        return ['ok'=>true];
    }
}
