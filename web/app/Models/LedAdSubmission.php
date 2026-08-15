<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A merchant's self-service submission ("¿Tu negocio acepta Bitcoin?") for
 * the header LED ticker — reviewed and approved/rejected from Filament
 * (App\Filament\Resources\LedAdSubmissionResource) before it becomes a
 * live App\Models\LedAd. Never shown publicly on its own.
 */
class LedAdSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'category',
        'description',
        'address',
        'city',
        'business_hours',
        'accepts_lightning',
        'accepts_onchain',
        'message',
        'url',
        'contact_name',
        'contact_email',
        'contact_phone',
        'status',
        'admin_notes',
        'led_ad_id',
    ];

    protected function casts(): array
    {
        return [
            'accepts_lightning' => 'boolean',
            'accepts_onchain' => 'boolean',
        ];
    }

    public function ledAd(): BelongsTo
    {
        return $this->belongsTo(LedAd::class);
    }
}
