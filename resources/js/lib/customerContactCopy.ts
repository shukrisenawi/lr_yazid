export const customerContactCopy = {
    title: 'Correct customer contact details',
    help: 'Only the current customer name and Malaysian phone can be corrected here. Email/login, passwords, roles, verification, addresses and VIP access are not editable. Existing orders, invoices, payment records and booking snapshots remain unchanged.',
    name: 'Customer name',
    phone: 'Malaysian phone number',
    reason: 'Reason for correction (admin only)',
    save: 'Save contact correction',
    confirm:
        'Save this customer contact correction? The previous and new values, your administrator ID and reason will be recorded.',
    reload: 'Reload latest customer profile',
    history: 'Administrator contact corrections',
    historyHelp:
        'Only administrator corrections are listed here. Customer self-service changes also advance the profile version but do not create an administrator correction.',
    empty: 'No administrator contact corrections.',
    before: 'Previous',
    after: 'New',
    actor: 'Administrator',
    revision: 'Profile version',
} as const;
