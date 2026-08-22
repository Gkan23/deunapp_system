<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'courier_id',
        'route_status_id',
        'route_date',
        'started_at',
        'finished_at',
        'estimated_distance_km',
    ];

    protected function casts(): array
    {
        return [
            'route_date' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'estimated_distance_km' => 'decimal:2',
        ];
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function routeStatus(): BelongsTo
    {
        return $this->belongsTo(RouteStatus::class);
    }

    public function routeShipments(): HasMany
    {
        return $this->hasMany(RouteShipment::class);
    }
}
