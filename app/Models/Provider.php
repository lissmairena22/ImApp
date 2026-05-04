<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Provider extends Model {
    protected $fillable = ['company_name', 'ruc', 'phone', 'email', 'address', 'is_active'];
    public function compras() { return $this->hasMany(Purchase::class); }
}
