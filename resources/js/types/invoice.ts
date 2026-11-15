export type Invoice = {
    id: number;
    reference: string;
    issued_at: string;
    snapshot: {
        seller: {
            seller_name: string;
            seller_address: string;
            seller_email: string;
            registration_number: string | null;
            footer_note: string | null;
        };
        buyer: {
            customer_name: string;
            customer_email: string;
            customer_phone: string;
            shipping_address: Record<string, string | null> | null;
        };
        order_reference: string;
        payment_reference: string;
        paid_at: string;
        currency: string;
        subtotal_sen: number;
        shipping_fee_sen: number;
        total_sen: number;
        items: {
            product_name: string;
            variant_name: string | null;
            sku: string;
            quantity: number;
            unit_price_sen: number;
            line_total_sen: number;
        }[];
    };
};
