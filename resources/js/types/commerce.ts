export type Address = {
    id: number;
    full_name: string;
    phone: string;
    line1: string;
    line2: string | null;
    city: string;
    state: string;
    postcode: string;
    country_code: string;
    is_default: boolean;
};
export type CartLine = {
    id: number;
    product_id: number;
    product_variant_id: number | null;
    name: string;
    slug: string;
    variant_name: string | null;
    sku: string;
    image_url: string | null;
    quantity: number;
    available: number;
    unit_price_sen: number;
    line_total_sen: number;
    error: string | null;
};
export type CartSummary = {
    items: CartLine[];
    subtotal_sen: number;
    shipping_fee_sen: number;
    total_sen: number;
    valid: boolean;
    shipping_enabled: boolean;
    requires_shipping: boolean;
};
export type OrderItem = {
    id: number;
    product_name: string;
    variant_name: string | null;
    sku: string;
    quantity: number;
    unit_price_sen: number;
    line_total_sen: number;
};
export type Order = {
    cancellation?: {
        id: number;
        actor_id: number | null;
        source: string;
        previous_payment_status: string;
        previous_fulfillment_status: string | null;
        reason: string;
        created_at: string;
    } | null;
    invoice?: { id: number; reference: string } | null;
    courier_name?: string | null;
    tracking_number?: string | null;
    fulfillment_history?: {
        id: number;
        admin_id: number;
        previous_status: string;
        new_status: string;
        reason: string;
        created_at: string;
    }[];
    id: number;
    reference: string;
    customer_name: string;
    customer_email: string;
    customer_phone: string;
    shipping_address: Omit<Address, 'id' | 'is_default'> | null;
    subtotal_sen: number;
    shipping_fee_sen: number;
    total_sen: number;
    payment_status: string;
    fulfillment_status: string | null;
    notes: string | null;
    expires_at: string;
    created_at: string;
    cancellation_reason: string | null;
    items: OrderItem[];
    payments?: Payment[];
};
export type Payment = {
    id: number;
    order_id: number;
    reference: string;
    environment: string;
    amount_sen: number;
    status: string;
    bill_code: string | null;
    transaction_reference: string | null;
    requires_review: boolean;
    review_reason: string | null;
    paid_at: string | null;
    checked_at: string | null;
};
export const dateTime = (value: string) =>
    new Intl.DateTimeFormat('en-MY', {
        timeZone: 'Asia/Kuala_Lumpur',
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
