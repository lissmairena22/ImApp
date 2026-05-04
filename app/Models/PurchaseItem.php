<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model {
    public $timestamps = false;
    protected $fillable = ['purchase_id', 'product_id', 'quantity', 'cost_price', 'subtotal'];
    public function compra() { return $this->belongsTo(Purchase::class, 'purchase_id'); }
public function producto() { return $this->belongsTo(Product::class); }
}
