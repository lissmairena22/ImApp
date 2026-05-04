<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegister extends Model
{
    protected $fillable = [
        'user_id', 'opened_at', 'closed_at', 'initial_balance',
        'cash_sales', 'cash_out', 'system_balance',
        'physical_balance', 'difference', 'status', 'notes'
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

public function movimientos() { return $this->hasMany(CashMovement::class); }
}
