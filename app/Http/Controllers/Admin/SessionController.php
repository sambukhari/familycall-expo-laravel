<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class SessionController extends Controller {
 public function loginPage(){return \Inertia\Inertia::render('Login');}
 public function login(Request $r){
  $data=$r->validate(['email'=>'required|email','password'=>'required|string']);
  $allowed=(string)config('services.family_admin_email');
  if ($allowed==='' || strcasecmp($data['email'],$allowed)!==0 || !Auth::attempt($data)){
   throw ValidationException::withMessages(['email'=>'Invalid admin credentials.']);
  }
  $r->session()->regenerate();
  return redirect()->route('family.admin.dashboard');
 }
 public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect()->route('login');}
}
