<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerImpersonationController extends Controller
{
    public function store(Request $request, User $customer): RedirectResponse
    {
        abort_unless($customer->role === UserRole::Customer, 404);
        abort_if($request->session()->has('impersonation'), 403);

        $adminId = $request->user()->id;
        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::guard('web')->login($customer);
        $request->session()->put('impersonation', [
            'admin_id' => $adminId,
            'customer_id' => $customer->id,
        ]);

        return to_route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $identity = $request->session()->get('impersonation');
        abort_unless(is_array($identity) && ($identity['customer_id'] ?? null) === $request->user()->id, 403);
        $admin = User::find($identity['admin_id'] ?? null);
        abort_unless($admin?->role === UserRole::Admin && $admin->hasVerifiedEmail(), 403);

        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::guard('web')->login($admin);

        return to_route('admin.customers.index');
    }
}
