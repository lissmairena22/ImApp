<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'user_id', 'order_date', 'estimated_delivery_date',
        'type', 'priority', 'status', 'estimated_price', 'advance_payment'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function production()
    {
        return $this->hasOne(Production::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function inventoryOutput()
    {
        return $this->hasOne(InventoryOutput::class);
    }
}
