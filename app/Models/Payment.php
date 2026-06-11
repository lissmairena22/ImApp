<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'user_id', 'credit_id', 'payment_date', 'amount', 'payment_method', 'notes'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function credit()
    {
        return $this->belongsTo(Credit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
