<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model {
    protected $fillable = [
        'invoice_number', 'client_id', 'order_id', 'user_id',
        'invoice_date', 'subtotal', 'tax', 'discount', 'total', 'status'
    ];


    public function cliente() { return $this->belongsTo(Client::class); }
public function pedido() { return $this->belongsTo(Order::class); }
public function usuario() { return $this->belongsTo(User::class); }
public function items() { return $this->hasMany(InvoiceItem::class); }
public function credito() { return $this->hasOne(Credit::class); }
public function pagos() { return $this->hasMany(Payment::class); }
}
