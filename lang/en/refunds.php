<?php

return [
    'positive' => 'Record a positive MYR amount with at most two decimal places.',
    'reason' => 'Provide a reason between 5 and 1,000 characters.',
    'key_conflict' => 'This submission key already belongs to a different refund record. Reload before recording another refund.',
    'unverified' => 'Select a matching server-verified paid MYR payment for this order.',
    'date' => 'The refund time must be between the verified payment time and now (Malaysia time).',
    'excess' => 'The amount exceeds the remaining unrecorded balance for this payment.',
    'duplicate' => 'The refund reference or submission key already exists. Check the ledger before trying again.',
    'saved' => 'Manual refund recorded. No money was transferred by this application.',
];
