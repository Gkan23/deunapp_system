<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courier extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_provider_id',
        'user_id',
        'license_number',
        'is_available',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function deliveryProvider(): BelongsTo
    {
        return $this->belongsTo(DeliveryProvider::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(CourierLocation::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }
}
