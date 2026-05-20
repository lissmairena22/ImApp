<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model {
    public $timestamps = false; // Esta tabla no tiene created_at/updated_at en tu SQL
    protected $fillable = [
        'order_id', 'product_id', 'description', 'quantity',
        'unit_price', 'subtotal', 'measurements', 'material',
        'material_lost', 'print_type', 'finish'
    ];

public function pedido() { return $this->belongsTo(Order::class, 'order_id'); }
public function producto() { return $this->belongsTo(Product::class, 'product_id'); }
public function materialConsumptions() { return $this->hasMany(OrderItemMaterial::class); }
}
