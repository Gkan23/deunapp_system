<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'courier_id',
        'vehicle_type_id',
        'vehicle_status_id',
        'plate_number',
        'brand',
        'model',
        'color',
    ];

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function vehicleStatus(): BelongsTo
    {
        return $this->belongsTo(VehicleStatus::class);
    }
}
