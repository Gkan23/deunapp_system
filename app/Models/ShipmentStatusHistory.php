<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'shipment_status_history';
    protected $fillable = [
        'shipment_id',
        'shipment_status_id',
        'changed_by_user_id',
        'comment',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function shipmentStatus(): BelongsTo
    {
        return $this->belongsTo(ShipmentStatus::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'changed_by_user_id'
        );
    }
}
