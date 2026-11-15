<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'payment' => ['nullable', 'in:pending,paid,failed,cancelled,refunded'], 'fulfillment' => ['nullable', 'in:new,processing,packed,shipped,delivered,cancelled']]);

        return Inertia::render('admin/orders/Index', [
            'orders' => Order::when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('reference', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%')->orWhere('customer_email', 'like', '%'.$search.'%')))
                ->when($filters['payment'] ?? null, fn ($q, $value) => $q->where('payment_status', $value))
                ->when($filters['fulfillment'] ?? null, fn ($q, $value) => $q->where('fulfillment_status', $value))->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function show(Order $order): Response
    {
        return Inertia::render('admin/orders/Show', ['order' => $order->load(['items', 'payments', 'fulfillmentHistory'])]);
    }

    public function update(Request $request, Order $order, OrderFulfillmentService $service): RedirectResponse
    {
        $data = $request->validate(['fulfillment_status' => ['required', 'in:new,processing,packed,shipped,delivered'],
            'courier_name' => ['nullable', 'string', 'max:100'], 'tracking_number' => ['nullable', 'string', 'max:150'],
            'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->update($order, $request->user(), $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('fulfillment.saved')]);

        return back();
    }
}
