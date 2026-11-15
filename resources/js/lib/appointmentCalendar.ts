const dateFormatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Kuala_Lumpur',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});
export const malaysiaDate = (value: string) =>
    dateFormatter.format(new Date(value));
export function calendarDays(month: string): string[] {
    const [year, number] = month.split('-').map(Number);
    const date = new Date(Date.UTC(year, number - 1, 1));
    date.setUTCDate(1 - ((date.getUTCDay() + 6) % 7));
    return Array.from({ length: 42 }, (_, index) =>
        new Date(date.getTime() + index * 86400000).toISOString().slice(0, 10),
    );
}
export function shiftMonth(month: string, direction: number): string {
    const [year, number] = month.split('-').map(Number);
    return new Date(Date.UTC(year, number - 1 + direction, 1))
        .toISOString()
        .slice(0, 7);
}
