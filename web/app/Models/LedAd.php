<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** A single message in the header's LED-ticker carousel — see App\View\Composers\LedDisplayComposer. */
class LedAd extends Model
{
    use HasFactory;

    protected $fillable = [
        'message',
        'url',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('led-display:data'));
        static::deleted(fn () => Cache::forget('led-display:data'));
    }
}
