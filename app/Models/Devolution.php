<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Devolution extends Model
{
    use HasFactory;

    protected $fillable = ['invoice_id', 'user_id', 'devolution_date', 'reason', 'amount_returned'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(DevolutionItem::class);
    }
}
