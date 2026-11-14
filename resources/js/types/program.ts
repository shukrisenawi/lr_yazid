export type VipProgram = {
    id: number;
    title: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    starts_at: string;
    ends_at: string;
    location: string;
    maps_url: string | null;
    registration_opens_at: string;
    registration_closes_at: string;
    capacity: number;
    registered_count: number;
    registration_enabled: boolean;
    status: string;
    images: { id: number; url: string }[];
};
export type ProgramRegistration = {
    id: number;
    reference: string;
    status: string;
    attendance: string;
    registered_at: string;
    program: VipProgram;
    cancellation_reason: string | null;
    user?: { name: string; email: string; phone: string | null };
};
export const localDateTime = (value?: string) =>
    value
        ? new Intl.DateTimeFormat('sv-SE', {
              timeZone: 'Asia/Kuala_Lumpur',
              year: 'numeric',
              month: '2-digit',
              day: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
              second: '2-digit',
              hour12: false,
          })
              .format(new Date(value))
              .replace(' ', 'T')
        : '';
export const registrationOpen = (program: VipProgram, now = Date.now()) =>
    program.status === 'published' &&
    program.registration_enabled &&
    new Date(program.registration_opens_at).getTime() <= now &&
    new Date(program.registration_closes_at).getTime() > now &&
    new Date(program.starts_at).getTime() > now;
