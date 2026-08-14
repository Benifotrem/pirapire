<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'currency',
        'order_type',
        'min_amount',
        'max_amount',
        'payment_methods',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'payment_methods' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
