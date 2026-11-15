<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function show(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice->order);

        return Inertia::render('invoice/Show', ['invoice' => $invoice,
            'backUrl' => ($request->user()->role === UserRole::Admin ? '/admin/orders/' : '/orders/').$invoice->order_id]);
    }

    public function issue(Request $request, Order $order, InvoiceService $service): RedirectResponse
    {
        $invoice = $service->issue($order, $request->user());

        return to_route('invoices.show', $invoice);
    }

    public function settings(): Response
    {
        return Inertia::render('admin/orders/InvoiceSettings', ['settings' => InvoiceSetting::current()]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'],
            'seller_name' => ['required', 'string', 'max:200'], 'seller_address' => ['required', 'string', 'max:2000'],
            'seller_email' => ['required', 'email', 'max:254'], 'registration_number' => ['nullable', 'string', 'max:100'],
            'footer_note' => ['nullable', 'string', 'max:1000']]);
        InvoiceSetting::current();
        DB::transaction(fn () => InvoiceSetting::whereKey(1)->lockForUpdate()->firstOrFail()->update($data), 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('documents.settings_saved')]);

        return back();
    }
}
