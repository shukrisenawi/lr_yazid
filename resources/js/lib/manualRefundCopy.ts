export const manualRefundCopy = {
    title: 'Manual refund ledger',
    help: 'Record only a refund already completed outside this application. This action does not transfer money, call ToyyibPay, void a bill, change the original payment/invoice, return stock, cancel fulfillment or revoke VIP. Handle those operational decisions separately.',
    review: 'Verified payments flagged for review may be recorded here. Recording a refund does not clear their review flag.',
    immutable:
        'Records are append-only: verify the details carefully. A submission retry cannot create another record, and the cumulative amount cannot exceed the selected payment.',
    noEligible:
        'No verified paid MYR payment with an unrecorded balance is available.',
    payment: 'Verified payment',
    choose: 'Choose a payment',
    balance: 'Unrecorded balance',
    amount: 'Refund amount (MYR)',
    external: 'External refund / receipt reference',
    externalHelp:
        '3–100 letters, numbers or . _ : / -; stored in uppercase and unique within the payment environment.',
    method: 'Refund method',
    methods: {
        bank_transfer: 'Bank transfer',
        cash: 'Cash receipt',
        other: 'Other external method',
    },
    date: 'Refund completed at (Malaysia time)',
    reason: 'Reason / audit note (admin only)',
    confirm:
        'I confirm this refund was already completed externally and the details are correct.',
    submit: 'Record completed refund',
    dialog: 'Record this completed external refund? This only creates an audit record; it sends no money and cannot be edited or deleted.',
    empty: 'No manual refund records.',
    total: 'Total recorded refunds',
    paymentId: 'Payment',
    actor: 'Recorded by',
    recordedAt: 'Recorded at',
    sandbox: 'Sandbox record — not a live refund',
    customerHelp:
        'Refund entries were recorded manually by an administrator. They do not prove arrival in your account. Original payment and invoice details remain unchanged.',
} as const;
