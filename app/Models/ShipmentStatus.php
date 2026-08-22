<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'status_name',
        'description',
    ];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class);
    }
}
