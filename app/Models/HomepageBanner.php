<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/** @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 */
class HomepageBanner extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'version' => 'integer', 'position' => 'integer'];
    }

    /** @return list<array<string, mixed>> */
    public static function visible(): array
    {
        $now = now();

        return array_values(self::where('status', 'published')->whereNotNull('image_path')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderBy('position')->orderBy('id')->limit(6)->get()
            ->map(fn ($banner) => [
                ...$banner->only(['id', 'title', 'subtitle', 'image_alt', 'link_label', 'link_path']),
                'image_url' => Storage::disk('public')->url($banner->image_path),
            ])->all());
    }
}
