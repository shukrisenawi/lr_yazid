<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['show_in_footer' => 'boolean', 'version' => 'integer'];
    }
}
