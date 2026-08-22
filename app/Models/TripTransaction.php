<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_provider_id',
        'recharge_id',
        'trip_id',
        'transaction_type',
        'quantity',
        'description',
        'transaction_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'transaction_at' => 'datetime',
        ];
    }

    public function deliveryProvider(): BelongsTo
    {
        return $this->belongsTo(DeliveryProvider::class);
    }

    public function recharge(): BelongsTo
    {
        return $this->belongsTo(Recharge::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
