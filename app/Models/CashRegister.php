<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegister extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'opened_at', 'closed_at', 'initial_balance',
        'cash_sales', 'cash_out', 'system_balance',
        'physical_balance', 'difference', 'status', 'notes'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashMovements()
    {
        return $this->hasMany(CashMovement::class);
    }
}
