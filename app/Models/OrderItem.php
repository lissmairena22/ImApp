<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    public $timestamps = false; // This table does not have created_at/updated_at columns.
    protected $fillable = [
        'order_id', 'product_id', 'description', 'quantity',
        'unit_price', 'subtotal', 'measurements', 'material',
        'material_lost', 'print_type', 'finish'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function materialConsumptions()
    {
        return $this->hasMany(OrderItemMaterial::class);
    }
}
