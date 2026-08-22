<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_service_id',
        'customer_id',
        'punctuality_score',
        'customer_service_score',
        'package_condition_score',
        'overall_score',
        'comment',
        'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'punctuality_score' => 'integer',
            'customer_service_score' => 'integer',
            'package_condition_score' => 'integer',
            'overall_score' => 'decimal:2',
            'rated_at' => 'datetime',
        ];
    }

    public function deliveryService(): BelongsTo
    {
        return $this->belongsTo(DeliveryService::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
