<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str; // Importamos Str para manipular textos

class Unit extends Model {
    protected $fillable = ['name', 'abbreviation'];

    // Este método se ejecuta automáticamente
    protected static function booted()
    {
        static::creating(function ($unit) {
            // Si no se envió una abreviación, la autogeneramos
            if (empty($unit->abbreviation)) {
                // Toma los primeros 3 caracteres del nombre y los pone en mayúsculas
                $unit->abbreviation = Str::upper(Str::substr($unit->name, 0, 3));
            }
        });
    }

    public function productos() {
        return $this->hasMany(Product::class);
    }
}
