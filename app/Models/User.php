<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable {
    use Notifiable;
    protected $fillable = ['name', 'username', 'password', 'role', 'status'];
    protected $hidden = ['password', 'remember_token'];

    public function cajas() { return $this->hasMany(CashRegister::class); }
public function pedidos() { return $this->hasMany(Order::class); }
}
