<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A freelancer's pitch on an open EscrowJob — see EscrowService::applyToJob(). */
class EscrowJobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'escrow_job_id',
        'freelancer_customer_id',
        'message',
        'status',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(EscrowJob::class, 'escrow_job_id');
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'freelancer_customer_id');
    }
}
