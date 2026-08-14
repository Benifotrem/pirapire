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
        'hold_invoice',
        'payment_hash',
        'preimage',
        'payout_destination',
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

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'counterparty_customer_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(EscrowDispute::class);
    }
}
