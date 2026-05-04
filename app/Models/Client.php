<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'dni', 'phone', 'email', 'address', 'is_active'];
    public function facturas() { return $this->hasMany(Invoice::class); }
public function pedidos() { return $this->hasMany(Order::class); }
}
