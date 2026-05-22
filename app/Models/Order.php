<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'client_id', 'user_id', 'order_date', 'estimated_delivery_date',
        'type', 'priority', 'status', 'estimated_price', 'advance_payment'
    ];

public function cliente()
{
    return $this->belongsTo(Client::class, 'client_id');
}public function usuario() { return $this->belongsTo(User::class, 'user_id'); }
public function items() { return $this->hasMany(OrderItem::class); }
public function produccion() { return $this->hasOne(Production::class); }
public function factura() { return $this->hasOne(Invoice::class); }
public function inventoryOutput() { return $this->hasOne(InventoryOutput::class); }
}
