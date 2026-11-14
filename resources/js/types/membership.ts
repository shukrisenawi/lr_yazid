import type { Product, Paginated } from '@/types/catalog';
export type MembershipPlan = {
    id: number;
    name: string;
    price_sen: number;
    duration_months: number;
    benefits: string[];
    is_active: boolean;
    product: Product | null;
};
export type Membership = {
    id: number;
    membership_plan_id: number;
    status: string;
    starts_at: string;
    expires_at: string;
    plan: MembershipPlan;
};
export type MembershipHistory = {
    id: number;
    type: string;
    amount_sen: number;
    paid_at: string;
    expires_at: string;
};
export type MembershipPageProps = {
    plans: MembershipPlan[];
    memberships: Membership[];
    history: Paginated<MembershipHistory>;
};
