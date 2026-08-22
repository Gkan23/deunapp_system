<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_provider_id',
        'recharge_package_id',
        'trip_type_id',
        'trip_quantity',
        'commission_amount',
        'amount',
        'payment_reference',
        'recharged_at',
    ];

    protected function casts(): array
    {
        return [
            'trip_quantity' => 'integer',
            'commission_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'recharged_at' => 'datetime',
        ];
    }

    public function deliveryProvider(): BelongsTo
    {
        return $this->belongsTo(DeliveryProvider::class);
    }

    public function rechargePackage(): BelongsTo
    {
        return $this->belongsTo(RechargePackage::class);
    }

    public function tripType(): BelongsTo
    {
        return $this->belongsTo(TripType::class);
    }

    public function tripTransactions(): HasMany
    {
        return $this->hasMany(TripTransaction::class);
    }
}
