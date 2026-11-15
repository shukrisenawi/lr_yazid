<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property array{title: string, message: string, url: string, recipient_email: string} $snapshot */
class CommerceNoticeDelivery extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'queued_at' => 'immutable_datetime',
            'database_sent_at' => 'immutable_datetime', 'mail_sent_at' => 'immutable_datetime',
            'database_suppressed_at' => 'immutable_datetime', 'mail_suppressed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
