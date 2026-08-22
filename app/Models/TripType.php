<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripType extends Model
{
    use HasFactory;

    protected $fillable = [
        'type_name',
        'description',
    ];

    public function commissionRules(): HasMany
    {
        return $this->hasMany(CommissionRule::class);
    }

    public function recharges(): HasMany
    {
        return $this->hasMany(Recharge::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function deliveryServices(): HasMany
    {
        return $this->hasMany(DeliveryService::class);
    }
}
