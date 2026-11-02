<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateFamilyAdmin extends Command {
 protected $signature='familycall:admin-create';
 protected $description='Securely create or update the configured FamilyCall administrator';
 public function handle():int {
  $email=(string)config('services.family_admin_email');
  if(!$email || !filter_var($email,FILTER_VALIDATE_EMAIL)){
   $this->error('Set FAMILY_ADMIN_EMAIL in .env and clear config cache first.');
   return self::FAILURE;
  }
  $name=$this->ask('Admin display name','FamilyCall Admin');
  $password=$this->secret('New admin password (minimum 12 characters)');
  if(!is_string($password)||strlen($password)<12){
   $this->error('Password must contain at least 12 characters.');
   return self::FAILURE;
  }
  $verify=$this->secret('Repeat password');
  if($password!==$verify){$this->error('Passwords do not match.');return self::FAILURE;}
  User::updateOrCreate(['email'=>$email],['name'=>$name,'password'=>Hash::make($password)]);
  $this->info('Administrator account saved: '.$email);
  return self::SUCCESS;
 }
}
