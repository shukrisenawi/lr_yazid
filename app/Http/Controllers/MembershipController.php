<?php

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
use App\Models\MembershipTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render($request->user() ? 'customer/Membership' : 'public/Membership', [
            'plans' => MembershipPlan::with('product.images')->where('is_active', true)->whereHas('product', fn ($query) => $query->where('status', 'active'))->get(),
            'memberships' => $request->user()?->memberships()->with('plan')->orderByDesc('expires_at')->get() ?? [],
            'history' => MembershipTransaction::where('user_id', $request->user()->id ?? 0)->latest()->paginate(10),
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $membership = $request->user()->memberships()->with('plan')->where('status', 'active')
            ->where('starts_at', '<=', now())->where('expires_at', '>', now())->orderByDesc('expires_at')->firstOrFail();

        return Inertia::render('vip/Dashboard', [
            'membership' => $membership,
            'daysRemaining' => max(0, (int) ceil(($membership->expires_at->getTimestamp() - now()->getTimestamp()) / 86400)),
        ]);
    }
}
