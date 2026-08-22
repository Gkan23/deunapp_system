<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_code',
        'customer_id',
        'sender_id',
        'recipient_id',
        'origin_address_id',
        'destination_address_id',
        'origin_branch_id',
        'destination_branch_id',
        'shipment_status_id',
        'requested_at',
        'scheduled_at',
        'estimated_delivery_at',
        'delivered_at',
        'declared_value',
        'delivery_instructions',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'estimated_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'declared_value' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(ShipmentPerson::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(ShipmentPerson::class, 'recipient_id');
    }

    public function originAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'origin_address_id');
    }

    public function destinationAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'destination_address_id');
    }

    public function originBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'origin_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function shipmentStatus(): BelongsTo
    {
        return $this->belongsTo(ShipmentStatus::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class);
    }

    public function routeShipments(): HasMany
    {
        return $this->hasMany(RouteShipment::class);
    }

    public function deliveryProof(): HasOne
    {
        return $this->hasOne(DeliveryProof::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function deliveryService(): HasOne
    {
        return $this->hasOne(DeliveryService::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }
}
