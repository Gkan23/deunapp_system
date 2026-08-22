<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'reported_by_user_id',
        'incident_type_id',
        'incident_status_id',
        'description',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reported_by_user_id'
        );
    }

    public function incidentType(): BelongsTo
    {
        return $this->belongsTo(IncidentType::class);
    }

    public function incidentStatus(): BelongsTo
    {
        return $this->belongsTo(IncidentStatus::class);
    }
}
