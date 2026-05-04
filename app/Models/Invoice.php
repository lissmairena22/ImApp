<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model {
    protected $fillable = [
        'invoice_number', 'client_id', 'order_id', 'user_id',
        'invoice_date', 'subtotal', 'tax', 'discount', 'total', 'status'
    ];

    public function client(): BelongsTo {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function order(): BelongsTo {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany {
        return $this->hasMany(InvoiceItem::class);
    }

    public function credit(): HasOne {
        return $this->hasOne(Credit::class);
    }

    public function payments(): HasMany {
        return $this->hasMany(Payment::class);
    }
}
