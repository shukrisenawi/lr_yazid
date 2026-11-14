export type AppointmentService = {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    default_capacity: number;
    location: string;
    is_active: boolean;
    slots_count: number;
    minimum_notice_hours: number;
    maximum_advance_days: number;
    cancellation_notice_hours: number;
    maximum_active_bookings: number;
};
export type AppointmentSlot = {
    id: number;
    starts_at: string;
    ends_at: string;
    capacity: number;
    location: string;
    notes: string | null;
    status: string;
    appointments_count: number;
};
export type SlotGeneration = {
    preview: boolean;
    candidates: number;
    created: number;
    overlaps: number;
    breaks: number;
    blocked_days: number;
    past: number;
    slots: { starts_at: string; ends_at: string }[];
};
