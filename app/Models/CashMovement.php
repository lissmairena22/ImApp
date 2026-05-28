<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'cash_register_id', 'user_id', 'type', 'concept', 'amount', 'movement_date'
    ];

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
