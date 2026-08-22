<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'address_id',
        'branch_name',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function originShipments(): HasMany
    {
        return $this->hasMany(
            Shipment::class,
            'origin_branch_id'
        );
    }

    public function destinationShipments(): HasMany
    {
        return $this->hasMany(
            Shipment::class,
            'destination_branch_id'
        );
    }
}
