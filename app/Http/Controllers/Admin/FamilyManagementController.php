<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\FamilyAccount;
use Illuminate\Http\Request;

class FamilyManagementController extends Controller
{
    public function create(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        Family::create($data);
        return back()->with('notice', 'Family created');
    }

    public function assign(Request $request, FamilyAccount $account)
    {
        $data = $request->validate(['family_id' => ['nullable', 'integer', 'exists:families,id']]);
        $account->update(['family_id' => $data['family_id'] ?? null]);
        return back()->with('notice', 'Family membership updated');
    }
}
