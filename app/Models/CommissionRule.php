<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_type_id',
        'commission_amount',
        'commission_percentage',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'commission_amount' => 'decimal:2',
            'commission_percentage' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function tripType(): BelongsTo
    {
        return $this->belongsTo(TripType::class);
    }

    public function rechargePackages(): HasMany
    {
        return $this->hasMany(RechargePackage::class);
    }
}
