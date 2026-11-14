<?php

return [
    'service_saved' => 'Appointment service saved. Existing slots are unchanged.',
    'slot_saved' => 'Appointment slot saved.',
    'slot_deleted' => 'Unused appointment slot deleted.',
    'overlap' => 'This time overlaps an existing slot for this service.',
    'blocked' => 'This date is blocked for this service.',
    'booked' => 'Slots with approved appointments cannot be changed. Reschedule or cancel the appointments first.',
    'used' => 'Slots with appointment history cannot be deleted. Disable an unused future slot instead.',
    'inactive' => 'Activate this service before creating or generating slots.',
    'range' => 'Generate at most 93 days at a time.',
    'limit' => 'This configuration exceeds the limit of 5,000 slots. Reduce the date range or increase the duration.',
    'blocked_saved' => 'Date blocked. Unconfirmed slots on this date are disabled.',
    'blocked_removed' => 'Blocked date removed. Existing disabled slots must be re-enabled individually.',
    'blocked_approved' => 'This date has approved appointments. Reschedule or cancel them before blocking the date.',
    'generated' => ':count slots generated. Existing and overlapping slots were preserved.',
];
