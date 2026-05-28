<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceItem extends Model {
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'subtotal',
        'measurements',
        'material',
        'material_lost'
    ];

    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function product(): BelongsTo {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function materialConsumptions(): HasMany {
        return $this->hasMany(OrderItemMaterial::class);
    }

    public function devolutionItems(): HasMany {
        return $this->hasMany(DevolutionItem::class);
    }
}
