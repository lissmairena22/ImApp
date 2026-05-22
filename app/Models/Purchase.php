<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model {
    protected $fillable = [
        'provider_id',
        'user_id',
        'purchase_date',
        'provider_invoice_number',
        'total',
        // Campos agregados para que Laravel permita guardarlos:
        'payment_method',
        'amount_cordobas',
        'amount_dolares',
        'exchange_rate'
    ];

    public function proveedor() {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function usuario() {
        return $this->belongsTo(User::class);
    }

    public function items() {
        return $this->hasMany(PurchaseItem::class);
    }
}
