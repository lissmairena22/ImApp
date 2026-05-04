<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model {
    protected $fillable = [
        'cash_register_id', 'user_id', 'type', 'concept', 'amount', 'movement_date'
    ];
    public function caja() { return $this->belongsTo(CashRegister::class, 'cash_register_id'); }
public function usuario() { return $this->belongsTo(User::class); }
}
