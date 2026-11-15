export type CommerceDelivery = {
    id: number;
    kind: string;
    queued_at: string;
    database_sent_at: string | null;
    mail_sent_at: string | null;
    database_suppressed_at: string | null;
    mail_suppressed_at: string | null;
    database_suppression_reason: string | null;
    mail_suppression_reason: string | null;
    user: { id: number; name: string; email: string };
    order: { id: number; reference: string } | null;
    payment: { id: number; reference: string; environment: string } | null;
};
