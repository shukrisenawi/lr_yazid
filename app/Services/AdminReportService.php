<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VipProgram;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AdminReportService
{
    /** @param array{from: string, to: string, environment: string} $filters
     * @return array<string, mixed>
     */
    public function generate(array $filters): array
    {
        return DB::transaction(function () use ($filters) {
            $now = CarbonImmutable::now();
            $from = CarbonImmutable::parse($filters['from'])->startOfDay();
            $until = CarbonImmutable::parse($filters['to'])->addDay()->startOfDay();
            $paid = $this->verifiedPayments($filters['environment'])->where('p.paid_at', '>=', $from)->where('p.paid_at', '<', $until);
            $refunds = DB::table('manual_refunds')->where('currency', 'MYR')->where('environment', $filters['environment'])
                ->where('refunded_at', '>=', $from)->where('refunded_at', '<', $until);
            $sales = (clone $paid)->selectRaw('count(*) as paid_orders, coalesce(sum(p.amount_sen), 0) as collected_sen')->first();
            $membershipItems = (clone $paid)->join('order_items as oi', 'oi.order_id', '=', 'o.id')->where('oi.type', 'membership');
            $transactions = (clone $membershipItems)->join('membership_transactions as mt', function ($join) {
                $join->on('mt.order_item_id', '=', 'oi.id')->on('mt.payment_id', '=', 'p.id')->on('mt.user_id', '=', 'o.user_id');
            })->whereColumn('mt.amount_sen', 'oi.line_total_sen');
            $orders = Order::where('created_at', '>=', $from)->where('created_at', '<', $until);
            $appointments = Appointment::where('created_at', '>=', $from)->where('created_at', '<', $until);
            $summary = [
                'collected_sen' => (int) $sales->collected_sen,
                'manual_refund_sen' => (int) (clone $refunds)->sum('amount_sen'),
                'manual_refunds' => (clone $refunds)->count(),
                'membership_sen' => (int) (clone $membershipItems)->sum('oi.line_total_sen'),
                'paid_orders' => (int) $sales->paid_orders,
                'orders_created' => (clone $orders)->count(),
                'new_customers' => User::where('role', 'customer')->where('created_at', '>=', $from)->where('created_at', '<', $until)->count(),
                'vip_activations' => (clone $transactions)->where('mt.type', 'activation')->distinct()->count('mt.user_id'),
                'vip_renewals' => (clone $transactions)->where('mt.type', 'renewal')->count(),
                'appointment_requests' => (clone $appointments)->count(),
                'excluded_paid_attempts' => max(0, DB::table('payments')->where('environment', $filters['environment'])->where('status', 'paid')
                    ->where('paid_at', '>=', $from)->where('paid_at', '<', $until)->count() - (int) $sales->paid_orders),
            ];
            $monthly = [];
            $salesMonths = $this->months(clone $paid, 'p.paid_at', 'sum(p.amount_sen) as collected_sen, count(*) as paid_orders');
            $refundMonths = $this->months(clone $refunds, 'refunded_at', 'sum(amount_sen) as manual_refund_sen');
            $membershipMonths = $this->months(clone $membershipItems, 'p.paid_at', 'sum(oi.line_total_sen) as membership_sen');
            $activationMonths = $this->months((clone $transactions)->where('mt.type', 'activation'), 'p.paid_at', 'count(distinct mt.user_id) as vip_activations');
            $renewalMonths = $this->months((clone $transactions)->where('mt.type', 'renewal'), 'p.paid_at', 'count(*) as vip_renewals');
            $orderMonths = $this->months((clone $orders)->toBase(), 'created_at', 'count(*) as orders_created');
            $appointmentMonths = $this->months((clone $appointments)->toBase(), 'created_at', 'count(*) as appointment_requests');
            for ($month = $from->startOfMonth(); $month->lt($until); $month = $month->addMonth()) {
                $key = $month->format('Y-m');
                $monthly[] = [
                    'month' => $key, 'collected_sen' => (int) ($salesMonths[$key]->collected_sen ?? 0),
                    'manual_refund_sen' => (int) ($refundMonths[$key]->manual_refund_sen ?? 0),
                    'membership_sen' => (int) ($membershipMonths[$key]->membership_sen ?? 0),
                    'paid_orders' => (int) ($salesMonths[$key]->paid_orders ?? 0),
                    'orders_created' => (int) ($orderMonths[$key]->orders_created ?? 0),
                    'vip_activations' => (int) ($activationMonths[$key]->vip_activations ?? 0),
                    'vip_renewals' => (int) ($renewalMonths[$key]->vip_renewals ?? 0),
                    'appointment_requests' => (int) ($appointmentMonths[$key]->appointment_requests ?? 0),
                ];
            }
            $statuses = [];
            $statusCounts = (clone $appointments)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            foreach (['pending', 'approved', 'rejected', 'cancelled', 'completed', 'no_show', 'rescheduled'] as $status) {
                $statuses[$status] = (int) ($statusCounts[$status] ?? 0);
            }

            return ['filters' => $filters, 'as_of' => $now->toIso8601String(), 'summary' => $summary,
                'current' => $this->current($now, $filters['environment']), 'monthly' => $monthly, 'appointments' => $statuses,
                'labels' => __('reports.metrics'), 'statusLabels' => __('reports.statuses')];
        });
    }

    private function verifiedPayments(string $environment): Builder
    {
        return DB::table('payments as p')->join('orders as o', 'o.id', '=', 'p.order_id')
            ->where('p.environment', $environment)->where('p.status', 'paid')->where('p.requires_review', false)
            ->whereNotNull('p.paid_at')->whereNotNull('p.checked_at')->whereNotNull('p.transaction_reference')->where('p.transaction_reference', '!=', '')
            ->where('o.payment_status', 'paid')->where('o.currency', 'MYR')->whereColumn('p.amount_sen', 'o.total_sen')
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('payments as flagged')->whereColumn('flagged.order_id', 'o.id')->where('flagged.requires_review', true))
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('payments as duplicate')->whereColumn('duplicate.order_id', 'o.id')->where('duplicate.status', 'paid')->whereColumn('duplicate.id', '!=', 'p.id'));
    }

    /** @param literal-string $column
     * @param  literal-string  $aggregates
     * @return array<string, object>
     */
    private function months(Builder $query, string $column, string $aggregates): array
    {
        // Fixed, application-owned SQL expressions shared by MySQL and SQLite.
        return $query->selectRaw("substr($column, 1, 7) as month, $aggregates")->groupByRaw("substr($column, 1, 7)")->get()->keyBy('month')->all();
    }

    /** @return array<string, int> */
    private function current(CarbonImmutable $now, string $environment): array
    {
        $customers = User::where('role', 'customer');
        $active = fn ($query) => $query->where('status', 'active')->where('starts_at', '<=', $now)->where('expires_at', '>', $now);
        $total = (clone $customers)->count();
        $vip = (clone $customers)->whereHas('memberships', $active)->count();

        return [
            'customers' => $total, 'normal_customers' => $total - $vip, 'active_vip' => $vip,
            'expired_vip' => (clone $customers)->whereDoesntHave('memberships', $active)->whereHas('memberships', fn ($query) => $query->where('expires_at', '<=', $now))->count(),
            'expiring_vip' => (clone $customers)->whereHas('memberships', fn ($query) => $active($query)->where('expires_at', '<=', $now->addDays(30)))->count(),
            'products' => Product::count(), 'total_orders' => Order::count(),
            'pending_orders' => Order::where('payment_status', 'pending')->count(),
            'upcoming_programs' => VipProgram::where('status', 'published')->where('starts_at', '>=', $now)->count(),
            'pending_appointments' => Appointment::where('status', 'pending')->count(),
            'today_appointments' => Appointment::where('status', 'approved')->where('scheduled_starts_at', '>=', $now->startOfDay())->where('scheduled_starts_at', '<', $now->addDay()->startOfDay())->count(),
            'upcoming_appointments' => Appointment::where('status', 'approved')->where('scheduled_starts_at', '>=', $now)->count(),
            'review_payments' => DB::table('payments')->where('environment', $environment)->where('requires_review', true)->count(),
            'undated_paid_attempts' => DB::table('payments')->where('environment', $environment)->where('status', 'paid')->whereNull('paid_at')->count(),
        ];
    }
}
