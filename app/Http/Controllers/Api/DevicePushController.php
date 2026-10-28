<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\FamilyDeviceToken;
use Illuminate\Http\Request;
class DevicePushController extends Controller {
    public function store(Request $request) {
        $data=$request->validate(['token'=>'required|string|max:512','platform'=>'required|in:android']);
        $device=(string)$request->header('X-Device-ID');
        FamilyDeviceToken::updateOrCreate(['token'=>$data['token']], ['device_id'=>$device,'platform'=>$data['platform']]);
        return response()->json(['ok'=>true]);
    }
}
