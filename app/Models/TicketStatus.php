<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'status_name',
    ];

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }
}
