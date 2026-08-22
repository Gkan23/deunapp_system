<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_name',
    ];

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }
}
