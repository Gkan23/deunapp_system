<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_provider_id',
        'trip_type_id',
        'status',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    public function deliveryProvider(): BelongsTo
    {
        return $this->belongsTo(DeliveryProvider::class);
    }

    public function tripType(): BelongsTo
    {
        return $this->belongsTo(TripType::class);
    }

    public function deliveryService(): HasOne
    {
        return $this->hasOne(DeliveryService::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TripTransaction::class);
    }
}
