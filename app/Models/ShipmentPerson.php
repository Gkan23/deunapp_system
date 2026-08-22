<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentPerson extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'identity_number',
        'email',
        'person_type',
    ];

    public function senderShipments(): HasMany
    {
        return $this->hasMany(
            Shipment::class,
            'sender_id'
        );
    }

    public function recipientShipments(): HasMany
    {
        return $this->hasMany(
            Shipment::class,
            'recipient_id'
        );
    }
}
