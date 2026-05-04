<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'unit_id', 'name', 'type',
        'sale_price', 'cost_price', 'stock', 'min_stock',
        'manage_stock', 'estimated_production_time', 'is_active'
    ];

    public function categoria() { return $this->belongsTo(Category::class); }
    public function unidad() { return $this->belongsTo(Unit::class); }
}
