<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'status_name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
