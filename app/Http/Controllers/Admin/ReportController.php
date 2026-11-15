<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Services\AdminReportService;
use App\Support\CsvCell;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportRequest $request, AdminReportService $service): Response
    {
        return Inertia::render($request->routeIs('admin.dashboard') ? 'admin/Dashboard' : 'admin/reports/Index', [
            'report' => $service->generate($request->filters()),
        ]);
    }

    public function export(ReportRequest $request, AdminReportService $service): StreamedResponse
    {
        $report = $service->generate($request->filters());

        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('The report export stream could not be opened.');
            }
            $write = function (array $values) use ($stream): void {
                fputcsv($stream, array_map(fn ($value) => CsvCell::safe((string) $value), $values), escape: '');
            };
            $write(['Environment', $report['filters']['environment'], 'From (MYT)', $report['filters']['from'], 'To (MYT)', $report['filters']['to']]);
            $write(['Generated at', $report['as_of']]);
            $write(['Section', 'Metric', 'Value', 'Unit']);
            foreach (['summary' => __('reports.period'), 'current' => __('reports.current')] as $section => $label) {
                foreach ($report[$section] as $key => $value) {
                    $isMoney = str_ends_with($key, '_sen');
                    $write([$label, $report['labels'][$key], $isMoney ? $this->decimal($value) : $value, $isMoney ? 'MYR' : 'count']);
                }
            }
            $columns = ['collected_sen', 'manual_refund_sen', 'membership_sen', 'paid_orders', 'orders_created', 'vip_activations', 'vip_renewals', 'appointment_requests'];
            $write([__('reports.month'), ...array_map(fn ($key) => $report['labels'][$key], $columns)]);
            foreach ($report['monthly'] as $row) {
                $write([$row['month'], ...array_map(fn ($key) => str_ends_with($key, '_sen') ? $this->decimal($row[$key]) : $row[$key], $columns)]);
            }
            $write([__('reports.appointments'), __('reports.count')]);
            foreach ($report['appointments'] as $status => $count) {
                $write([$report['statusLabels'][$status], $count]);
            }
            fclose($stream);
        }, 'admin-report-'.$report['filters']['environment'].'-'.$report['filters']['from'].'-'.$report['filters']['to'].'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function decimal(int $sen): string
    {
        return intdiv($sen, 100).'.'.sprintf('%02d', $sen % 100);
    }
}
