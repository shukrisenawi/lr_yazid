import type { ReportFilters } from '@/types/reports';
export const reportExportUrl = (filters: ReportFilters) =>
    '/admin/reports/export?' + new URLSearchParams(filters).toString();
export const barPercent = (value: number, maximum: number) =>
    maximum > 0 ? Math.max(0, Math.min(100, (value / maximum) * 100)) : 0;
