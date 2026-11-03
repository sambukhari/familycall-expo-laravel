<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyAccount;
use App\Models\FamilyMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FamilyMessageController extends Controller
{
    private function participants(Request $request, int $id): array
    {
        $account = $request->attributes->get('family_account');
        $member = FamilyAccount::whereKey($id)->where('active', true)->firstOrFail();
        abort_unless($account->family_id && $member->family_id === $account->family_id && $member->id !== $account->id, 403);
        return ['account:'.$account->id, 'account:'.$member->id];
    }

    public function index(Request $request, int $member)
    {
        [$me, $other] = $this->participants($request, $member);
        $before = max(0, (int) $request->query('before', 0));
        $query = FamilyMessage::query()->where(function ($q) use ($me, $other) {
            $q->where(fn($p) => $p->where('sender_id', $me)->where('recipient_id', $other))
              ->orWhere(fn($p) => $p->where('sender_id', $other)->where('recipient_id', $me));
        });
        if ($before) $query->where('id', '<', $before);
        $messages = $query->orderByDesc('id')->limit(51)->get();
        $hasMore = $messages->count() > 50;
        return ['messages' => $messages->take(50)->reverse()->values(), 'has_more' => $hasMore];
    }

    public function store(Request $request, int $member)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $body = trim($data['body']);
        abort_if($body === '', 422, 'Message cannot be empty');
        $account = $request->attributes->get('family_account');
        $message = DB::transaction(function () use ($account, $member, $body) {
            $accounts = FamilyAccount::whereKey([$account->id, $member])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $sender = $accounts->get($account->id);
            $recipient = $accounts->get($member);
            abort_unless($sender && $recipient && $sender->active && $recipient->active && $sender->id !== $recipient->id
                && $sender->family_id && $sender->family_id === $recipient->family_id, 403);
            return FamilyMessage::create(['sender_id'=>'account:'.$sender->id, 'recipient_id'=>'account:'.$recipient->id, 'body'=>$body]);
        });
        return response()->json(['message'=>$message], 201);
    }

    public function markRead(Request $request, int $member)
    {
        [$me, $other] = $this->participants($request, $member);
        FamilyMessage::where('sender_id', $other)->where('recipient_id', $me)
            ->whereNull('read_at')->update(['read_at'=>now()]);
        return ['ok'=>true];
    }
}
