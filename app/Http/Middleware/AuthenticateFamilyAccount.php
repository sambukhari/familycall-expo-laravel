<?php
namespace App\Http\Middleware;

use App\Models\FamilyAccountToken;
use Closure;
use Illuminate\Http\Request;

class AuthenticateFamilyAccount
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();
        abort_unless(is_string($bearer) && $bearer !== '', 401, 'Unauthenticated');
        $token = FamilyAccountToken::with('account')->where('token_hash', hash('sha256', $bearer))->first();
        abort_unless($token && $token->account && $token->account->active
            && (!$token->expires_at || $token->expires_at->isFuture()), 401, 'Unauthenticated');
        $request->attributes->set('family_account', $token->account);
        $request->attributes->set('family_account_token', $token);
        return $next($request);
    }
}
