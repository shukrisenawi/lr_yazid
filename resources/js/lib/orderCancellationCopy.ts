export const orderCancellationCopy = {
    title: 'Cancel unpaid order',
    help: 'Only unpaid orders without payment review or fulfillment progress can be cancelled. Active stock reservations will be released. Existing provider bills are not voided; any later payment must be reviewed. This does not issue a refund.',
    reason: 'Cancellation reason (visible to the customer)',
    submit: 'Cancel unpaid order',
    confirm:
        'Cancel this unpaid order and release its stock reservations? This cannot be undone.',
    history: 'Cancellation record',
    actor: 'Actor',
    previous: 'Previous payment / fulfillment',
} as const;
