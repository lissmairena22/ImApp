<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model {
    public $timestamps = false;
    protected $fillable = ['invoice_id', 'product_id', 'description', 'quantity', 'unit_price', 'subtotal'];
    public function factura() { return $this->belongsTo(Invoice::class); }
public function producto() { return $this->belongsTo(Product::class); }
}
