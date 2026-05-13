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


    public function getRequiresProductionAttribute(): bool
    {
        return $this->type === 'Servicio' && ($this->estimated_production_time > 0);
    }

    // Relaciones
    public function categoria() { return $this->belongsTo(Category::class, 'category_id'); }
    public function unidad() { return $this->belongsTo(Unit::class, 'unit_id'); }
}

