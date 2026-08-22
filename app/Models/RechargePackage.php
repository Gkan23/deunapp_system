<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RechargePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'commission_rule_id',
        'package_name',
        'trip_quantity',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trip_quantity' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function commissionRule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class);
    }

    public function recharges(): HasMany
    {
        return $this->hasMany(Recharge::class);
    }
}
