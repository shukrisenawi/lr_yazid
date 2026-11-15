export type ReportFilters = {
    from: string;
    to: string;
    environment: 'production' | 'sandbox';
};
export type ReportMonth = {
    month: string;
    collected_sen: number;
    membership_sen: number;
    paid_orders: number;
    orders_created: number;
    vip_activations: number;
    vip_renewals: number;
    appointment_requests: number;
};
export type AdminReport = {
    filters: ReportFilters;
    as_of: string;
    summary: Record<string, number>;
    current: Record<string, number>;
    monthly: ReportMonth[];
    appointments: Record<string, number>;
    labels: Record<string, string>;
    statusLabels: Record<string, string>;
};
