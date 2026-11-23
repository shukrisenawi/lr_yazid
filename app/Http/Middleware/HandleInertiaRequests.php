<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\ContentPage;
use App\Models\Order;
use App\Models\Payment;
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
            'unreadNotificationCount' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            'menuBadges' => fn () => $request->user()?->role === UserRole::Admin ? [
                '/admin/orders' => Order::where('payment_status', 'paid')->where('fulfillment_status', 'new')->count(),
                '/admin/payments' => Payment::where('requires_review', true)->count(),
                '/admin/appointments' => Appointment::where('status', 'pending')->count(),
            ] : [],
            'auth' => [
                'impersonating' => $request->user() !== null
                    && $request->session()->get('impersonation.customer_id') === $request->user()->id,
                'isVip' => $request->user()?->hasActiveMembership() ?? false,
                'user' => $request->user()?->only(['id', 'name', 'email', 'phone', 'role', 'avatar', 'email_verified_at', 'created_at', 'updated_at', 'profile_version']),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
