<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model {
    protected $fillable = [
        'invoice_id', 'user_id', 'credit_id', 'payment_date', 'amount', 'payment_method', 'notes'
    ];
    public function factura() { return $this->belongsTo(Invoice::class); }
public function credito() { return $this->belongsTo(Credit::class); }
public function usuario() { return $this->belongsTo(User::class); }
}
