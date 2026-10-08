<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeeSettingsRequest;
use App\Models\FeeSetting;
use App\Services\FeeSetupService;
use Illuminate\Support\Facades\DB;

class FeeSettingsController extends Controller
{
    public function index()
    {
        return view('fees.settings', ['settings' => FeeSetting::findOrFail(1)]);
    }

    public function update(FeeSettingsRequest $request)
    {
        DB::transaction(function () use ($request) {
            $data = $request->validated();
            foreach (['fine_amount', 'fine_max_cap', 'sibling_discount_value'] as $field) {
                $data[$field] = $data[$field] ?? 0;
            }
            FeeSetting::whereKey(1)->lockForUpdate()->firstOrFail()->update($data);
            FeeSetupService::log('Fee settings updated.', 'fee_setup', 'fee_settings:1');
        });

        return back()->with('success', 'Fee settings saved.');
    }
}
