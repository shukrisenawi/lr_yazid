<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MembershipAdjustmentRequest;
use App\Models\Membership;
use App\Services\MembershipLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate(['status' => ['nullable', 'in:active,expired,expiring'], 'search' => ['nullable', 'string', 'max:100']]);
        $query = Membership::with(['user:id,name,email,phone', 'plan']);
        if (($data['status'] ?? '') === 'expired') {
            $query->where('expires_at', '<=', now());
        } elseif (in_array($data['status'] ?? '', ['active', 'expiring'], true)) {
            $query->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now());
            if ($data['status'] === 'expiring') {
                $query->where('expires_at', '<=', now()->addDays(30));
            }
        }
        $query->when($data['search'] ?? null, fn ($query, $search) => $query->whereHas('user', fn ($users) => $users->where(fn ($names) => $names->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))));

        return Inertia::render('admin/Memberships', ['memberships' => $query->latest()->paginate(20)->withQueryString(), 'filters' => $data]);
    }

    public function adjust(MembershipAdjustmentRequest $request, Membership $membership, MembershipLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->adjust($membership, $request->user(), CarbonImmutable::parse($request->validated('expires_at'), 'Asia/Kuala_Lumpur'), $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Membership expiry adjusted. An audit record has been saved.']);

        return back();
    }
}
