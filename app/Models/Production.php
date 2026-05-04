<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Production extends Model {
    protected $fillable = [
        'order_id', 'user_id', 'start_date', 'end_date', 'estimated_time',
        'actual_time', 'production_cost', 'status', 'observations'
    ];

    public function pedido() { return $this->belongsTo(Order::class); }
public function usuario() { return $this->belongsTo(User::class); }
}
