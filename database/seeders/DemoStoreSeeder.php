<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ProductUnitService;
use App\Services\StoreManager;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

/**
 * TOKO CONTOH untuk mencoba alur lengkap:
 *   php artisan db:seed --class=DemoStoreSeeder
 *
 * Login: demo@toko.test / password123 (sudah disetujui, trial berjalan)
 * Isi: rokok multi-satuan (slop/bungkus/batang) dan susu ber-expire 2 batch.
 */
class DemoStoreSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'demo@toko.test')->exists()) {
            $this->command?->info('Toko contoh sudah ada.');

            return;
        }

        $stores = app(StoreManager::class);
        $user = $stores->register([
            'store_name' => 'Toko Contoh',
            'name' => 'Pemilik Contoh',
            'email' => 'demo@toko.test',
            'phone_number' => '081100000001',
            'password' => 'password123',
        ]);

        $superadmin = User::where('role', 'superadmin')->firstOrFail();
        $stores->approve($user->store, $superadmin);

        Tenant::run($user->store_id, function () use ($user) {
            $units = app(ProductUnitService::class);
            $inventory = app(InventoryService::class);

            $rokok = Category::create(['name' => 'Rokok']);
            $minuman = Category::create(['name' => 'Minuman']);
            $supplier = Supplier::create(['name' => 'Grosir Contoh', 'is_active' => true]);

            // ---------------- rokok: BATANG / BUNGKUS isi 16 / SLOP isi 160
            $a = Product::create([
                'category_id' => $rokok->id, 'name' => 'Rokok Contoh 16', 'description' => '-',
                'price' => 2500, 'stock' => 0, 'base_unit' => 'BATANG', 'status' => 1, 'is_favorite' => 1,
            ]);
            $units->sync($a, [
                ['name' => 'BUNGKUS', 'conversion' => 16, 'price' => 32000, 'barcode' => '8990000000016'],
                ['name' => 'SLOP', 'conversion' => 160, 'price' => 310000, 'barcode' => '8990000000160'],
            ]);

            // ---------------- susu: ber-expire, PCS / DUS isi 24
            $b = Product::create([
                'category_id' => $minuman->id, 'name' => 'Susu UHT Contoh 200ml', 'description' => '-',
                'price' => 6000, 'stock' => 0, 'base_unit' => 'PCS', 'status' => 1, 'is_favorite' => 0,
                'track_expiry' => true, 'expiry_warning_days' => 30,
            ]);
            $units->sync($b, [['name' => 'DUS', 'conversion' => 24, 'price' => 135000]]);

            $slop = $a->units()->where('name', 'SLOP')->first();
            $dus = $b->units()->where('name', 'DUS')->first();

            $stockIn = StockIn::create([
                'supplier_id' => $supplier->id, 'user_id' => $user->id, 'received_at' => now()->toDateString(),
                'total' => 0, 'payment_status' => 'paid', 'paid_at' => now()->toDateString(),
            ]);

            $lines = [
                [$a, $slop, 5, 290000, null],                         // 800 batang
                [$b, $dus, 2, 120000, now()->addDays(20)->toDateString()],  // 48 pcs, segera expire
                [$b, $dus, 3, 118000, now()->addMonths(4)->toDateString()], // 72 pcs
            ];

            $total = 0;
            foreach ($lines as [$product, $unit, $qty, $cost, $exp]) {
                $line = StockInItem::create([
                    'stock_in_id' => $stockIn->id, 'product_id' => $product->id, 'product_unit_id' => $unit->id,
                    'unit_name' => $unit->name, 'unit_conversion' => $unit->conversion, 'quantity' => $qty,
                    'base_quantity' => $qty * $unit->conversion, 'cost_price' => $cost,
                    'cost_per_base' => round($cost / $unit->conversion, 4), 'expiry_date' => $exp,
                ]);
                $inventory->receive($line, now());
                $total += $qty * $cost;
            }
            $stockIn->update(['total' => $total]);
        });

        $this->command?->info('Toko contoh dibuat: demo@toko.test / password123');
    }
}
