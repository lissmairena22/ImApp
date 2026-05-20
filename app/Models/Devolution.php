<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Devolution extends Model {
    protected $fillable = ['invoice_id', 'user_id', 'devolution_date', 'reason', 'amount_returned'];
    public function factura() { return $this->belongsTo(Invoice::class); }
public function usuario() { return $this->belongsTo(User::class); }
public function items() { return $this->hasMany(DevolutionItem::class); }
}
