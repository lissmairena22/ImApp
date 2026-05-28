<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'user_id',
        'purchase_date',
        'provider_invoice_number',
        'total',
        // Fields added so Laravel can persist them.
        'payment_method',
        'amount_cordobas',
        'amount_dolares',
        'exchange_rate'
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
