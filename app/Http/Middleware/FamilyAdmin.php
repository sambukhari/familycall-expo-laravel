<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class FamilyAdmin {
 public function handle(Request $request, Closure $next) {
  abort_unless(auth()->check(),403);
  $email=(string)config('services.family_admin_email');
  abort_unless($email!=='' && strcasecmp((string)auth()->user()->email,$email)===0,403,'Not an administrator');
  return $next($request);
 }
}
