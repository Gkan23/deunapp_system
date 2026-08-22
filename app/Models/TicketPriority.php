<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketPriority extends Model
{
    use HasFactory;

    protected $fillable = [
        'priority_name',
    ];

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }
}
