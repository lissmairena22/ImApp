<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model {
    protected $fillable = ['provider_id', 'user_id', 'purchase_date', 'provider_invoice_number', 'total'];
    public function proveedor() { return $this->belongsTo(Provider::class, 'provider_id'); }
public function usuario() { return $this->belongsTo(User::class); }
public function items() { return $this->hasMany(PurchaseItem::class); }
}
