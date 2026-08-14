<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscrowDispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'escrow_job_id',
        'opened_by_customer_id',
        'reason',
        'status',
        'resolution',
        'resolution_notes',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function escrowJob(): BelongsTo
    {
        return $this->belongsTo(EscrowJob::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'opened_by_customer_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
