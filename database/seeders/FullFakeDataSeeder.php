<?php

namespace Database\Seeders;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Devolution;
use App\Models\DevolutionItem;
use App\Models\InventoryOutput;
use App\Models\InventoryOutputItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemMaterial;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Production;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FullFakeDataSeeder extends Seeder
{
    protected int $target = 2500;

    public function run(): void
    {
        $this->ensureCount(User::class, $this->target);
        $this->ensureCount(Client::class, $this->target);
        $this->ensureCount(Category::class, $this->target);
        $this->ensureCount(Unit::class, $this->target);
        $this->ensureCount(Provider::class, $this->target);
        $this->ensureCount(Product::class, $this->target);

        $this->ensureCount(Purchase::class, $this->target);
        $this->ensureCount(PurchaseItem::class, $this->target);
        $this->ensureCount(Order::class, $this->target);
        $this->ensureCount(OrderItem::class, $this->target);
        $this->ensureCount(Invoice::class, $this->target);
        $this->ensureCount(InvoiceItem::class, $this->target);
        $this->ensureCount(Credit::class, $this->target);
        $this->ensureCount(Payment::class, $this->target);
        $this->ensureCount(Devolution::class, $this->target);
        $this->ensureCount(DevolutionItem::class, $this->target);
        $this->ensureCount(CashRegister::class, $this->target);
        $this->ensureCount(CashMovement::class, $this->target);
        $this->ensureCount(Production::class, $this->target);

        $this->seedInventoryOutputs();
        $this->seedInventoryOutputItems();
        $this->seedOrderItemMaterials();
        $this->seedServiceMaterials();
    }

    protected function ensureCount(string $modelClass, int $target): void
    {
        $current = $modelClass::count();

        if ($current < $target) {
            $modelClass::factory($target - $current)->create();
        }
    }

    protected function seedInventoryOutputs(): void
    {
        $orders = Order::query()->pluck('id')->all();
        $users = User::query()->pluck('id')->all();

        $rows = [];
        foreach ($orders as $index => $orderId) {
            $rows[] = [
                'order_id' => $orderId,
                'user_id' => $users[$index % count($users)],
                'output_date' => now()->subDays(rand(1, 365))->toDateTimeString(),
                'reason' => fake()->randomElement(['Venta', 'Producción', 'Devolución', 'Ajuste', 'Destrucción']),
                'notes' => fake()->optional()->paragraph(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('inventory_outputs')->upsert($rows, ['order_id'], ['user_id', 'output_date', 'reason', 'notes', 'updated_at']);
        }
    }

    protected function seedInventoryOutputItems(): void
    {
        $inventoryOutputs = InventoryOutput::query()->pluck('id')->all();
        $products = Product::query()->pluck('id')->all();

        $rows = [];
        foreach ($inventoryOutputs as $index => $inventoryOutputId) {
            $rows[] = [
                'inventory_output_id' => $inventoryOutputId,
                'product_id' => $products[$index % count($products)],
                'description' => fake('es_ES')->sentence(4),
                'source_type' => fake()->randomElement(['Venta', 'Producción', 'Devolución', 'Inventario']),
                'quantity' => fake()->randomFloat(2, 1, 30),
                'unit_name' => fake()->randomElement(['Unidad', 'Metro', 'Caja', 'Paquete']),
                'material_lost' => fake()->randomFloat(2, 0, 10),
                'affects_stock' => fake()->boolean(80),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('inventory_output_items')->insert($rows);
        }
    }

    protected function seedOrderItemMaterials(): void
    {
        $orderItems = OrderItem::query()->pluck('id')->all();
        $invoiceItems = InvoiceItem::query()->pluck('id')->all();
        $products = Product::query()->pluck('id')->all();

        $rows = [];
        foreach ($orderItems as $index => $orderItemId) {
            $rows[] = [
                'order_item_id' => $orderItemId,
                'invoice_item_id' => $invoiceItems[$index % count($invoiceItems)] ?? null,
                'material_id' => $products[$index % count($products)] ?? null,
                'material_name' => fake('es_ES')->words(2, true),
                'unit_name' => fake()->randomElement(['Unidad', 'Metro', 'Caja', 'Paquete']),
                'available_stock_snapshot' => fake()->randomFloat(2, 5, 100),
                'quantity_per_service' => fake()->randomFloat(2, 1, 5),
                'quantity_used' => fake()->randomFloat(2, 1, 10),
                'material_lost' => fake()->randomFloat(2, 0, 5),
                'total_consumed' => fake()->randomFloat(2, 1, 100),
            ];
        }

        if ($rows !== []) {
            DB::table('order_item_materials')->insert($rows);
        }
    }

    protected function seedServiceMaterials(): void
    {
        $services = Product::query()->pluck('id')->all();
        $materials = Product::query()->pluck('id')->all();

        if ($services === [] || $materials === []) {
            return;
        }

        $rows = [];
        for ($i = 0; $i < $this->target; $i++) {
            $rows[] = [
                'service_id' => $services[$i % count($services)],
                'material_id' => $materials[$i % count($materials)],
                'quantity' => fake()->randomFloat(2, 1, 20),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('service_materials')->insert($rows);
        }
    }
}
