<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Unit extends Model {
    protected $fillable = ['name', 'abbreviation'];

    protected static function booted()
    {
        static::creating(function ($unit) {
            if (empty($unit->abbreviation)) {
                $unit->abbreviation = Str::upper(Str::substr($unit->name, 0, 3));
            }
        });
    }

    public function productos() {
        return $this->hasMany(Product::class);
    }
}
