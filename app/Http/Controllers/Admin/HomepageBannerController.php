<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HomepageBannerRequest;
use App\Models\HomepageBanner;
use App\Services\WebsiteContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HomepageBannerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/website/Banners', ['banners' => HomepageBanner::orderBy('position')->orderBy('id')->paginate(20)]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/website/BannerEdit', ['banner' => null, 'imageUrl' => null]);
    }

    public function edit(HomepageBanner $banner): Response
    {
        return Inertia::render('admin/website/BannerEdit', [
            'banner' => $banner, 'imageUrl' => $banner->image_path ? Storage::disk('public')->url($banner->image_path) : null,
        ]);
    }

    public function store(HomepageBannerRequest $request, WebsiteContentService $service): RedirectResponse
    {
        $banner = $service->banner(null, $request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('website.banner_saved')]);

        return to_route('admin.banners.edit', $banner);
    }

    public function update(HomepageBannerRequest $request, HomepageBanner $banner, WebsiteContentService $service): RedirectResponse
    {
        $service->banner($banner, $request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('website.banner_saved')]);

        return back();
    }
}
