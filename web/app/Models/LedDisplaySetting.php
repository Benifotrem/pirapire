<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings for the header's LED ticker (on/off, color). Always
 * exactly one row (id=1) — use current() rather than querying directly.
 */
class LedDisplaySetting extends Model
{
    protected $fillable = [
        'enabled',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], ['enabled' => true, 'color' => 'red']);
    }
}
