export function orderExportUrl(
    filters: Record<string, string | null | undefined>,
): string {
    const values = Object.entries(filters).filter(
        (entry): entry is [string, string] =>
            entry[1] !== undefined && entry[1] !== null && entry[1] !== '',
    );
    return '/admin/orders/export?' + new URLSearchParams(values).toString();
}
