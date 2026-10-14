<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan daftar satuan sebuah produk.
 *
 * Satuan terkecil (conversion 1) SELALU ada dan diambil dari kolom produk
 * (base_unit, price, barcode). Satuan lain dikirim sebagai array:
 *   [['id' => ?, 'name' => 'BUNGKUS', 'conversion' => 16, 'price' => 32000, 'barcode' => '899...'], ...]
 *
 * Satuan yang dihapus dari daftar dinonaktifkan (bukan dihapus), supaya
 * riwayat order yang memakainya tetap bisa ditampilkan.
 */
class ProductUnitService
{
    public function sync(Product $product, array $extraUnits): void
    {
        $baseName = strtoupper(trim($product->base_unit ?: 'PCS'));
        $seen = [$baseName => true];
        $barcodes = array_filter([$product->barcode]);

        // ---------------- validasi
        $clean = [];
        foreach (array_values($extraUnits) as $i => $u) {
            $name = strtoupper(trim((string) ($u['name'] ?? '')));
            if ($name === '') {
                // baris kosong dilewati; tapi kalau isi/harga sudah diketik, namanya pasti lupa
                if (($u['conversion'] ?? '') !== '' && ($u['conversion'] ?? null) !== null
                    || ($u['price'] ?? '') !== '' && ($u['price'] ?? null) !== null) {
                    throw ValidationException::withMessages(["units.$i.name" => 'Nama satuan belum diisi (mis. SLOP).']);
                }
                continue;
            }
            $conv = round((float) ($u['conversion'] ?? 0), 3);
            $price = (float) ($u['price'] ?? 0);
            $barcode = trim((string) ($u['barcode'] ?? '')) ?: null;

            if (isset($seen[$name])) {
                throw ValidationException::withMessages(["units.$i.name" => "Satuan $name tercantum dua kali."]);
            }
            if ($conv <= 1) {
                throw ValidationException::withMessages(["units.$i.conversion" => "Isi $name harus lebih dari 1 $baseName."]);
            }
            if ($price < 0) {
                throw ValidationException::withMessages(["units.$i.price" => "Harga $name tidak valid."]);
            }
            if ($barcode !== null) {
                if (in_array($barcode, $barcodes, true)) {
                    throw ValidationException::withMessages(["units.$i.barcode" => "Barcode $barcode dipakai dua kali."]);
                }
                $barcodes[] = $barcode;
            }

            $seen[$name] = true;
            $clean[] = compact('name', 'conv', 'price', 'barcode') + ['id' => $u['id'] ?? null];
        }

        // barcode tidak boleh bentrok dengan produk/satuan lain di toko yang sama
        if ($barcodes) {
            $clash = ProductUnit::whereIn('barcode', $barcodes)->where('product_id', '!=', $product->id)->value('barcode')
                ?? Product::whereIn('barcode', $barcodes)->where('id', '!=', $product->id)->value('barcode');
            if ($clash) {
                throw ValidationException::withMessages(['units' => "Barcode $clash sudah dipakai produk lain."]);
            }
        }

        // ---------------- satuan terkecil
        $base = $this->syncBase($product);

        // ---------------- satuan lain
        $keep = [$base->id];
        foreach ($clean as $i => $u) {
            $unit = ($u['id'] ? ProductUnit::where('product_id', $product->id)->find($u['id']) : null)
                ?? ProductUnit::firstOrNew(['product_id' => $product->id, 'name' => $u['name']]);

            $unit->fill([
                'name' => $u['name'],
                'conversion' => $u['conv'],
                'price' => $u['price'],
                'barcode' => $u['barcode'],
                'is_base' => false,
                'sort' => $i + 1,
                'is_active' => true,
            ])->save();

            $keep[] = $unit->id;
        }

        ProductUnit::where('product_id', $product->id)
            ->whereNotIn('id', $keep)
            ->update(['is_active' => false, 'barcode' => null]);
    }

    /**
     * Baris satuan terkecil selalu cermin dari kolom produk (base_unit,
     * price, barcode). Dipanggil otomatis setiap produk disimpan — termasuk
     * dari import Excel — jadi setiap produk pasti punya minimal 1 satuan.
     */
    public function syncBase(Product $product): ProductUnit
    {
        $base = ProductUnit::withoutGlobalScope('store')
            ->firstOrNew(['product_id' => $product->id, 'is_base' => true]);

        $base->store_id = $product->store_id;
        $base->fill([
            'name' => strtoupper(trim($product->base_unit ?: 'PCS')),
            'conversion' => 1,
            'price' => $product->price ?? 0,
            'barcode' => $product->barcode,
            'sort' => 0,
            'is_active' => true,
        ]);

        if ($base->isDirty() || ! $base->exists) {
            $base->save();
        }

        return $base;
    }
}
