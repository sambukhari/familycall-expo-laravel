<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FamilyAccountController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[A-Za-z0-9_.-]+$/', 'unique:family_accounts,username'],
            'password' => ['required', 'string', 'min:10', 'max:128'],
            'device_type' => ['required', 'in:phone,tv'],
        ]);
        $account = FamilyAccount::create($data);
        return $this->issueToken($account, 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string', 'max:128'],
        ]);
        $account = FamilyAccount::where('username', $data['username'])->first();
        if (!$account || !Hash::check($data['password'], $account->password) || !$account->active) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        return $this->issueToken($account);
    }

    private function issueToken(FamilyAccount $account, int $status = 200)
    {
        $token = Str::random(80);
        FamilyAccountToken::create([
            'family_account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
        ]);
        return response()->json(['token' => $token, 'account' => $account], $status)
            ->header('Cache-Control', 'no-store');
    }

    public function me(Request $request)
    {
        return ['account' => $request->attributes->get('family_account')->load('family:id,name')];
    }

    public function members(Request $request)
    {
        $account = $request->attributes->get('family_account');
        if (!$account->family_id) return ['members' => []];
        return ['members' => FamilyAccount::where('family_id', $account->family_id)
            ->where('active', true)->whereKeyNot($account->id)
            ->orderBy('username')->get(['id', 'username', 'device_type'])];
    }

    public function logout(Request $request)
    {
        $request->attributes->get('family_account_token')->delete();
        return response()->noContent();
    }
}
