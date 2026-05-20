<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemMaterial extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_item_id',
        'invoice_item_id',
        'material_id',
        'material_name',
        'unit_name',
        'available_stock_snapshot',
        'quantity_per_service',
        'quantity_used',
        'material_lost',
        'total_consumed',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'material_id');
    }
}
