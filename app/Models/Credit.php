<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Credit extends Model {
    protected $fillable = [
        'invoice_id', 'total_amount', 'pending_balance', 'interest_rate',
        'start_date', 'due_date', 'status'
    ];
    public function factura() { return $this->belongsTo(Invoice::class); }
public function pagos() { return $this->hasMany(Payment::class); }
}
