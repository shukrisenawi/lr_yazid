<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShippingSettingsRequest;
use App\Models\CommerceSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ShippingSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/Shipping', ['settings' => CommerceSetting::current()]);
    }

    public function update(ShippingSettingsRequest $request): RedirectResponse
    {
        CommerceSetting::updateOrCreate(['id' => 1], $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.settings_saved')]);

        return back();
    }
}
