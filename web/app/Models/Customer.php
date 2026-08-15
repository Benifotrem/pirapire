<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * The sovereign end-user of pirapire.pro. Identity is the LNURL-auth
 * linking public key — there is no email or password on this model.
 * Distinct from App\Models\User, which is the Filament admin/staff account.
 */
class Customer extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'linking_key',
        'display_name',
        'telegram_chat_id',
        'last_login_at',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function vipSubscriptions(): HasMany
    {
        return $this->hasMany(VipSubscription::class);
    }

    public function escrowJobsCreated(): HasMany
    {
        return $this->hasMany(EscrowJob::class, 'creator_customer_id');
    }

    public function escrowJobsAsCounterparty(): HasMany
    {
        return $this->hasMany(EscrowJob::class, 'counterparty_customer_id');
    }

    public function isVip(): bool
    {
        return $this->vipSubscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
