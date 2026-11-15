<?php

namespace App\Http\Middleware;

use App\Models\ContentPage;
use App\Models\WebsiteSetting;
use App\Services\CartService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'website' => fn () => WebsiteSetting::publicData(),
            'copy' => __('interface'),
            'footerPages' => fn () => ContentPage::where('status', 'published')->where('show_in_footer', true)->orderBy('title')->get(['title', 'slug']),
            'cartCount' => fn () => app(CartService::class)->count($request),
            'auth' => [
                'isVip' => $request->user()?->hasActiveMembership() ?? false,
                'user' => $request->user()?->only(['id', 'name', 'email', 'phone', 'role', 'email_verified_at', 'created_at', 'updated_at']),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
