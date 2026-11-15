<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteSettingRequest;
use App\Models\WebsiteSetting;
use App\Services\WebsiteContentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteSettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/website/Settings', ['settings' => WebsiteSetting::current()]);
    }

    public function update(WebsiteSettingRequest $request, WebsiteContentService $service): RedirectResponse
    {
        $service->settings($request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('website.settings_saved')]);

        return back();
    }
}
