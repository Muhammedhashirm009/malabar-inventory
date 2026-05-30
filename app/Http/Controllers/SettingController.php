<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        // Settings are already loaded in config('settings') by AppServiceProvider
        return view('settings.index');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'shop_name'               => 'required|string|max:255',
            'shop_address'            => 'nullable|string',
            'shop_phone'              => 'nullable|string|max:50',
            'shop_email'              => 'nullable|email|max:255',
            'shop_gstin'              => 'nullable|string|max:50',
            'sale_invoice_prefix'     => 'required|string|max:20',
            'sale_invoice_suffix'     => 'nullable|string|max:20',
            'purchase_invoice_prefix' => 'required|string|max:20',
            'purchase_invoice_suffix' => 'nullable|string|max:20',
        ]);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value ?? '']
            );
        }

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}
