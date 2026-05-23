<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use Livewire\Volt\Volt;
use Illuminate\Foundation\Testing\DatabaseTransactions; // <-- CAMBIO AQUÍ

class InventoryComponentTest extends TestCase
{
    use DatabaseTransactions; // <-- CAMBIO AQUÍ

    public function test_abrir_empaque_actualiza_correctamente_el_stock()
    {
        // 1. Preparación (Arrange): Creamos la resma
        $resma = Product::create([
            'name' => 'Resma de Papel Bond',
            'type' => 'Producto',
            'stock' => 2,
            'items_per_unit' => 500,
            'category_id' => 1, // Asegúrate de que esta categoría exista en tu BD
            'unit_id' => 1,     // Asegúrate de que esta unidad exista en tu BD
            'sale_price' => 200,
        ]);

        // ... resto de tu código exactamente igual ...
        $hojasSueltas = Product::create([
            'name' => 'Resma de Papel Bond (Suelto/Unidad)',
            'type' => 'Producto',
            'stock' => 0,
            'items_per_unit' => 1,
            'parent_id' => $resma->id,
            'category_id' => 1,
            'unit_id' => 2,
            'sale_price' => 1,
        ]);

        Volt::test('pages.productos') // <-- Aquí ponemos la ruta real
            ->call('openPackage', $resma->id)
            ->assertHasNoErrors();

        $this->assertEquals(1, $resma->fresh()->stock);
        $this->assertEquals(500, $hojasSueltas->fresh()->stock);
    }
}
