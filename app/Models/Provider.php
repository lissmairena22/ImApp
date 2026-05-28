<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = ['company_name', 'ruc', 'phone', 'email', 'address', 'is_active'];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
