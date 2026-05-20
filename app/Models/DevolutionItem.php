<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevolutionItem extends Model
{
    protected $fillable = [
        'devolution_id',
        'invoice_item_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'amount_returned',
        'returned_to_stock',
        'materials_restored',
    ];

    public function devolution(): BelongsTo
    {
        return $this->belongsTo(Devolution::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
