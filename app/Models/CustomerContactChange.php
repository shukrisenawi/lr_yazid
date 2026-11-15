<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array{name: string, phone: string|null} $previous_values
 * @property array{name: string, phone: string|null} $new_values
 */
class CustomerContactChange extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['previous_values' => 'array', 'new_values' => 'array', 'profile_version' => 'integer'];
    }
}
