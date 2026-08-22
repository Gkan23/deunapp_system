<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryService extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'trip_id',
        'shipment_id',
        'service_type_id',
        'trip_type_id',
        'status',
        'requested_at',
        'accepted_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'delivery_fee',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'delivery_fee' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function tripType(): BelongsTo
    {
        return $this->belongsTo(TripType::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class);
    }
}
