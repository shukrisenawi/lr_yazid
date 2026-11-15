<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CustomerContactChange;
use App\Models\MembershipTransaction;
use App\Models\User;
use App\Models\VipProgramRegistration;
use App\Services\CustomerContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'membership' => ['nullable', 'in:normal,active,expired,expiring']]);
        $active = fn ($query) => $query->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now());
        $query = User::where('role', 'customer')->select(['id', 'name', 'email', 'phone', 'created_at', 'email_verified_at'])
            ->withCount('orders')->withExists(['memberships as is_vip' => $active])
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$value.'%')->orWhere('email', 'like', '%'.$value.'%')->orWhere('phone', 'like', '%'.$value.'%')));
        match ($filters['membership'] ?? '') {
            'active' => $query->whereHas('memberships', $active),
            'normal' => $query->whereDoesntHave('memberships'),
            'expired' => $query->whereDoesntHave('memberships', $active)->whereHas('memberships', fn ($q) => $q->where('expires_at', '<=', now())),
            'expiring' => $query->whereHas('memberships', fn ($q) => $active($q)->where('expires_at', '<=', now()->addDays(30))),
            default => null,
        };

        return Inertia::render('admin/customers/Index', ['customers' => $query->latest()->paginate(20)->withQueryString(), 'filters' => $filters]);
    }

    public function show(Request $request, User $customer): Response
    {
        abort_unless($customer->role->value === 'customer', 404);
        $data = $request->validate(['section' => ['nullable', 'in:orders,memberships,programs,appointments']]);
        $section = $data['section'] ?? 'orders';
        $records = match ($section) {
            'orders' => $customer->orders()->latest()->paginate(10),
            'memberships' => MembershipTransaction::where('user_id', $customer->id)->latest()->paginate(10),
            'programs' => VipProgramRegistration::where('user_id', $customer->id)->with('program:id,title,starts_at,ends_at,location,status')->latest()->paginate(10),
            'appointments' => Appointment::where('user_id', $customer->id)->latest()->paginate(10),
            default => abort(422),
        };

        return Inertia::render('admin/customers/Show', [
            'customer' => $customer->only(['id', 'name', 'email', 'phone', 'created_at', 'email_verified_at', 'profile_version']),
            'contactHistory' => CustomerContactChange::where('customer_id', $customer->id)->latest('id')->paginate(10, pageName: 'contactPage')->withQueryString(),
            'isVip' => $customer->hasActiveMembership(), 'section' => $section, 'records' => $records->withQueryString(),
            'memberships' => $customer->memberships()->with('plan')->orderByDesc('expires_at')->get(),
            'orderCount' => $customer->orders()->count(),
        ]);
    }

    public function update(Request $request, User $customer, CustomerContactService $service): RedirectResponse
    {
        $service->update($customer, $request->user(), $request->all());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('customer_contacts.saved')]);

        return back();
    }
}
