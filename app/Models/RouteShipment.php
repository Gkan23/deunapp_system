<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteShipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'shipment_id',
        'delivery_order',
        'delivery_status',
    ];

    protected function casts(): array
    {
        return [
            'delivery_order' => 'integer',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
