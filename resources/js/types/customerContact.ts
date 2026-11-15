export type CustomerContact = {
    id: number;
    name: string;
    phone: string | null;
    profile_version: number;
};
export type CustomerContactChange = {
    id: number;
    admin_id: number;
    profile_version: number;
    previous_values: { name: string; phone: string | null };
    new_values: { name: string; phone: string | null };
    reason: string;
    created_at: string;
};
