<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class PairSecret {
 public function handle(Request $request, Closure $next) {
  $device=$request->header('X-Device-ID','');
  $secret=$request->header('X-Pair-Secret','');
  abort_unless(in_array($device,['tv','phone'],true),403,'Unknown device');
  abort_unless(is_string($secret) && hash_equals((string)config('services.family_call_secret'),$secret),403,'Invalid pairing secret');
  return $next($request);
 }
}
