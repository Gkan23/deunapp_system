<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider_type_id',
        'business_name',
        'identity_number',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function providerType(): BelongsTo
    {
        return $this->belongsTo(ProviderType::class);
    }

    public function couriers(): HasMany
    {
        return $this->hasMany(Courier::class);
    }

    public function recharges(): HasMany
    {
        return $this->hasMany(Recharge::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function tripTransactions(): HasMany
    {
        return $this->hasMany(TripTransaction::class);
    }
}
