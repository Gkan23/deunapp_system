<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'performed_by_user_id',
        'table_name',
        'record_id',
        'action_type',
        'details',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'performed_by_user_id'
        );
    }
}
