<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'shipment_id',
        'ticket_category_id',
        'ticket_status_id',
        'ticket_priority_id',
        'assigned_to_user_id',
        'subject',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            TicketCategory::class,
            'ticket_category_id'
        );
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(
            TicketStatus::class,
            'ticket_status_id'
        );
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(
            TicketPriority::class,
            'ticket_priority_id'
        );
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to_user_id'
        );
    }

    public function messages(): HasMany
    {
        return $this->hasMany(
            SupportMessage::class,
            'ticket_id'
        );
    }
}
