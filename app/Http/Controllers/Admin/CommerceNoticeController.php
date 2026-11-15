<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommerceNoticeDelivery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommerceNoticeController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', 'in:welcome,order_placed,payment_success,payment_failed'],
            'delivery' => ['nullable', 'in:waiting,processed,suppressed']]);
        $query = CommerceNoticeDelivery::select(['id', 'user_id', 'order_id', 'payment_id', 'kind', 'queued_at',
            'database_sent_at', 'mail_sent_at', 'database_suppressed_at', 'mail_suppressed_at',
            'database_suppression_reason', 'mail_suppression_reason'])
            ->with(['user:id,name,email', 'order:id,reference', 'payment:id,reference,environment'])
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->where(fn ($query) => $query
                ->whereHas('user', fn ($user) => $user->where(fn ($user) => $user->where('name', 'like', '%'.$value.'%')->orWhere('email', 'like', '%'.$value.'%')))
                ->orWhereHas('order', fn ($order) => $order->where('reference', 'like', '%'.$value.'%'))));
        match ($filters['delivery'] ?? '') {
            'waiting' => $query->where(fn ($query) => $query->where(fn ($query) => $query->whereNull('database_sent_at')->whereNull('database_suppressed_at'))
                ->orWhere(fn ($query) => $query->whereNull('mail_sent_at')->whereNull('mail_suppressed_at'))),
            'processed' => $query->whereNotNull('database_sent_at')->whereNotNull('mail_sent_at'),
            'suppressed' => $query->where(fn ($query) => $query->whereNotNull('database_suppressed_at')->orWhereNotNull('mail_suppressed_at')),
            default => null,
        };

        return Inertia::render('admin/CommerceNotices', ['deliveries' => $query->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters, 'kindLabels' => __('commerce_notices.kinds'), 'reasonLabels' => __('commerce_notices.reasons')]);
    }
}
