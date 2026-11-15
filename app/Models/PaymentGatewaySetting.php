<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewaySetting extends Model
{
    protected $guarded = [];

    protected $hidden = ['sandbox_secret_key', 'production_secret_key'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'sandbox_secret_key' => 'encrypted', 'production_secret_key' => 'encrypted', 'version' => 'integer'];
    }

    public static function current(): self
    {
        return self::find(1) ?? new self(['id' => 1, ...config('toyyibpay'), 'version' => 0]);
    }

    public function configured(?string $environment = null): bool
    {
        $environment ??= $this->environment;

        return in_array($environment, ['sandbox', 'production'], true)
            && filled($this->getAttribute($environment.'_secret_key'))
            && filled($this->getAttribute($environment.'_category_code'));
    }
}
