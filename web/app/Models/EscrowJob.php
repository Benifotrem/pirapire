<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EscrowJob extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'creator_customer_id',
        'counterparty_customer_id',
        'description',
        'amount_sats',
        'fee_sats',
        'status',
        'funding_invoice',
        'payment_hash',
        'payout_destination',
        'freelancer_payout_invoice',
        'expires_at',
        'funded_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'funded_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $job) {
            $job->{$job->getKeyName()} ??= (string) Str::uuid();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'creator_customer_id');
    }

    /**
     * The freelancer doing the job — stored in the `counterparty_customer_id`
     * column (kept from the earlier design; nothing outside this model
     * referenced it, so it was safe to expose under its actual role instead
     * of the generic name). Set by EscrowService::acceptApplication() once
     * the client picks one of the applications() below.
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'counterparty_customer_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(EscrowJobApplication::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(EscrowDispute::class);
    }

    /** Short human-facing contract code shown in the UI, e.g. "#ESC-A1B2C3D4". */
    public function contractCode(): string
    {
        return '#ESC-'.strtoupper(substr($this->id, 0, 8));
    }
}
