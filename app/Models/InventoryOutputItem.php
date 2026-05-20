<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryOutputItem extends Model
{
    protected $fillable = [
        'inventory_output_id',
        'product_id',
        'description',
        'source_type',
        'quantity',
        'unit_name',
        'material_lost',
        'affects_stock',
    ];

    public function output(): BelongsTo
    {
        return $this->belongsTo(InventoryOutput::class, 'inventory_output_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
