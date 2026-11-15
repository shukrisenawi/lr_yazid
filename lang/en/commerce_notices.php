<?php

return [
    'welcome_title' => 'Welcome to your account',
    'welcome_body' => 'Your email has been verified. You can now manage orders, membership and bookings from your account.',
    'order_placed_title' => 'Your order has been placed',
    'order_placed_body' => 'Order :reference was placed for MYR :amount. This confirms order creation only. Open the order for its current payment status.',
    'payment_success_title' => 'Your payment has been verified',
    'payment_success_body' => 'Payment for order :reference, MYR :amount, was verified by the server. Open the order for the latest details.',
    'payment_failed_title' => 'Your payment attempt was unsuccessful',
    'payment_failed_body' => 'The provider reported an unsuccessful payment attempt for order :reference, MYR :amount. Check the current order status before trying again.',
    'sandbox' => 'This was a Sandbox test payment, not a live payment.',
    'action' => 'View account details',
    'kinds' => ['welcome' => 'Verified account welcome', 'order_placed' => 'Order placed',
        'payment_success' => 'Payment verified', 'payment_failed' => 'Payment attempt failed'],
    'reasons' => ['email_unverified' => 'Email is no longer verified', 'email_changed' => 'Recipient email changed',
        'order_unavailable' => 'Order ownership changed or order unavailable', 'payment_state_changed' => 'Payment state changed',
        'newer_payment_attempt' => 'A newer payment attempt exists'],
];
