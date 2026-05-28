<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'unit_id', 'name', 'type',
        'sale_price', 'cost_price', 'stock', 'min_stock',
        'manage_stock', 'estimated_production_time', 'is_active',
        'is_sellable', 'items_per_unit', 'parent_id',

    ];


    public function getRequiresProductionAttribute(): bool
    {
        return $this->type === 'Servicio' && ($this->estimated_production_time > 0);
    }

    // Relationship: A service contains many materials (physical products).
    public function materials()
    {
        return $this->belongsToMany(Product::class, 'service_materials', 'service_id', 'material_id')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
