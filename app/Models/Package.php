<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'weight',
        'height',
        'width',
        'length',
        'content_description',
        'is_fragile',
        'declared_value',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'height' => 'decimal:2',
            'width' => 'decimal:2',
            'length' => 'decimal:2',
            'declared_value' => 'decimal:2',
            'is_fragile' => 'boolean',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
