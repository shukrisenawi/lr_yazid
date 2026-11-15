<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\ManualRefundService;
use App\Services\OrderFulfillmentService;
use App\Support\CsvCell;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('admin/orders/Index', [
            'orders' => $this->query($filters)->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function show(Order $order): Response
    {
        return Inertia::render('admin/orders/Show', ['order' => $order->load(['items', 'payments', 'fulfillmentHistory', 'invoice', 'cancellation', 'manualRefunds']),
            'refundKey' => (string) Str::uuid(),
            'canCancel' => in_array($order->payment_status, ['pending', 'failed'], true)
                && in_array($order->fulfillment_status, [null, 'new'], true)
                && ! $order->payments->contains('status', 'paid') && ! $order->payments->contains('requires_review', true)]);
    }

    public function cancel(Request $request, Order $order, CheckoutService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->cancel($order, $data['reason'], $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('order_cancellation.saved')]);

        return back();
    }

    public function refund(Request $request, Order $order, ManualRefundService $service): RedirectResponse
    {
        $service->record($order, $request->user(), $request->only(['idempotency_key', 'payment_id', 'amount', 'external_reference', 'method', 'refunded_at', 'reason', 'confirmed']));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('refunds.saved')]);

        return back();
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

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($this->filters($request));
        if ((clone $query)->count() > 10000) {
            throw ValidationException::withMessages(['export' => 'Export at most 10,000 orders. Narrow the date range or filters.']);
        }

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('The export stream could not be opened.');
            }
            fputcsv($stream, ['Order', 'Customer', 'Email', 'Phone', 'Payment', 'Fulfillment', 'Currency', 'Total', 'Courier', 'Tracking', 'Created at (MYT)', 'Invoice'], escape: '');
            foreach ($query->with('invoice:id,order_id,reference')->orderBy('id')->lazyById(200) as $order) {
                $values = [$order->reference, $order->customer_name, $order->customer_email, $order->customer_phone,
                    $order->payment_status, $order->fulfillment_status ?? '', $order->currency,
                    intdiv($order->total_sen, 100).'.'.sprintf('%02d', $order->total_sen % 100),
                    $order->courier_name ?? '', $order->tracking_number ?? '', $order->created_at->format('Y-m-d H:i:s'), $order->invoice->reference ?? ''];
                fputcsv($stream, array_map(CsvCell::safe(...), $values), escape: '');
            }
            fclose($stream);
        }, 'orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate(['search' => ['nullable', 'string', 'max:100'],
            'payment' => ['nullable', 'in:pending,paid,failed,cancelled,refunded'],
            'fulfillment' => ['nullable', 'in:new,processing,packed,shipped,delivered,cancelled'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])]]);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<Order>
     */
    private function query(array $filters): Builder
    {
        return Order::when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('reference', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%')->orWhere('customer_email', 'like', '%'.$search.'%')))
            ->when($filters['payment'] ?? null, fn ($q, $value) => $q->where('payment_status', $value))
            ->when($filters['fulfillment'] ?? null, fn ($q, $value) => $q->where('fulfillment_status', $value))
            ->when($filters['from'] ?? null, fn ($q, $value) => $q->where('created_at', '>=', $value.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $value) => $q->where('created_at', '<=', $value.' 23:59:59'));
    }
}
