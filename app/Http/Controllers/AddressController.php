<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddressRequest;
use App\Models\Address;
use App\Services\AddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('customer/Addresses', [
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get(),
            'states' => AddressRequest::STATES,
        ]);
    }

    public function store(AddressRequest $request, AddressService $service): RedirectResponse
    {
        $service->save($request->user(), null, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.address_saved')]);

        return back();
    }

    public function update(AddressRequest $request, Address $address, AddressService $service): RedirectResponse
    {
        Gate::authorize('update', $address);
        $service->save($request->user(), $address, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.address_saved')]);

        return back();
    }

    public function destroy(Request $request, Address $address, AddressService $service): RedirectResponse
    {
        Gate::authorize('delete', $address);
        $service->delete($request->user(), $address);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.address_deleted')]);

        return back();
    }
}
