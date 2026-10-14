<?php

namespace App\Http\Controllers;

use App\Support\Tenant;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductImport;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Import produk dari Excel (.xlsx) / CSV — dua langkah:
 *   1. unggah  → file diperiksa, hasilnya ditampilkan (BELUM ada yang disimpan)
 *   2. konfirmasi → baris yang valid disimpan
 */
class ProductImportController extends Controller
{
    private const DIR = 'imports';

    /** Halaman unggah */
    public function create()
    {
        $this->cleanOldFiles();

        return view('pages.products.import', ['result' => null]);
    }

    /** Langkah 1: periksa file dan tampilkan pratinjau */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:5120',
        ], [
            'file.required' => 'Pilih file Excel atau CSV dulu.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['xlsx', 'csv', 'xls'], true)) {
            return back()->withErrors(['file' => 'Format file harus .xlsx atau .csv.']);
        }

        // simpan sementara supaya langkah 2 membaca file yang sama persis
        $token = (string) Str::uuid();
        $path = $file->storeAs(self::DIR, $token . '.' . $extension, 'local');

        try {
            $result = $this->analyze($path, $extension);
        } catch (RuntimeException $e) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => $e->getMessage()]);
        }

        if (! $result['ok']) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => $result['error']]);
        }

        session(['product_import' => [
            'token' => $token,
            'path' => $path,
            'extension' => $extension,
            'filename' => $file->getClientOriginalName(),
        ]]);

        return view('pages.products.import', [
            'result' => $result,
            'token' => $token,
            'filename' => $file->getClientOriginalName(),
        ]);
    }

    /** Langkah 2: simpan baris yang valid */
    public function store(Request $request)
    {
        $import = session('product_import');

        if (! $import || $request->input('token') !== $import['token'] || ! Storage::disk('local')->exists($import['path'])) {
            return redirect()
                ->route('products.import')
                ->withErrors(['file' => 'Sesi import sudah berakhir. Unggah ulang filenya.']);
        }

        try {
            $result = $this->analyze($import['path'], $import['extension']);
        } catch (RuntimeException $e) {
            return redirect()->route('products.import')->withErrors(['file' => $e->getMessage()]);
        }

        $overwriteStock = $request->boolean('overwrite_stock');

        // kategori "mirip" yang dipilih untuk digabung ke kategori lama: [ nama baru (huruf kecil) => id kategori lama ]
        $mergeInto = [];
        $chosen = (array) $request->input('merge', []);
        foreach ($result['categories'] as $category) {
            if ($category['similar'] && in_array($category['key'], $chosen, true)) {
                $mergeInto[$category['key']] = $category['similar']['id'];
            }
        }

        $created = 0;
        $updated = 0;
        $newCategories = 0;

        DB::transaction(function () use ($result, $overwriteStock, $mergeInto, &$created, &$updated, &$newCategories) {
            // kategori: cocokkan tanpa membedakan huruf besar/kecil
            $categories = Category::all(['id', 'name'])
                ->mapWithKeys(fn ($c) => [mb_strtolower(trim($c->name)) => $c->id])
                ->all();

            // salah ketik yang dipilih untuk digabung → arahkan ke kategori lama
            $categories = $mergeInto + $categories;

            foreach ($result['items'] as $item) {
                if ($item['state'] === 'error') {
                    continue;
                }

                $data = $item['data'];
                $categoryKey = mb_strtolower($data['category']);

                if (! isset($categories[$categoryKey])) {
                    $category = new Category();
                    $category->name = $data['category'];
                    $category->save();

                    $categories[$categoryKey] = $category->id;
                    $newCategories++;
                }

                /** @var Product|null $product */
                $product = $item['product_id'] ? Product::find($item['product_id']) : null;

                if ($product) {
                    // PERBARUI: sel kosong tidak menimpa data lama
                    $product->category_id = $categories[$categoryKey];
                    $product->price = $data['price'];

                    // dikenali lewat kode / barcode → nama di file dianggap nama terbaru
                    if (in_array($item['matched_by'] ?? null, ['code', 'barcode'], true)) {
                        $product->name = $data['name'];
                    }

                    // sel kosong (termasuk barcode) tidak menghapus isi lama
                    foreach (['code', 'barcode', 'cost_price', 'base_unit', 'description', 'status', 'is_favorite'] as $field) {
                        if ($data[$field] !== null) {
                            $product->{$field} = $data[$field];
                        }
                    }

                    // stok produk lama hanya diubah kalau diminta (barang datang → menu Stok Masuk)
                    if ($overwriteStock && $data['stock'] !== null && ! $product->track_expiry) {
                        $product->stock = $data['stock'];
                    }

                    $product->save();
                    $updated++;

                    continue;
                }

                // BARU
                $product = new Product();
                $product->code = $data['code'];
                // kosong → kode toko dibuat otomatis oleh model Product
                $product->barcode = $data['barcode'];
                $product->name = $data['name'];
                $product->category_id = $categories[$categoryKey];
                $product->price = $data['price'];
                $product->cost_price = $data['cost_price'];
                $product->stock = $data['stock'] ?? 0;
                $product->base_unit = $data['base_unit'] ?? 'PCS';
                $product->description = $data['description'];
                $product->status = $data['status'] ?? 1;
                $product->is_favorite = $data['is_favorite'] ?? 0;
                $product->save();

                $created++;
            }
        });

        Storage::disk('local')->delete($import['path']);
        session()->forget('product_import');

        $skipped = $result['counts']['error'];
        $message = "Import selesai: {$created} produk baru, {$updated} diperbarui"
            . ($newCategories ? ", {$newCategories} kategori baru" : '')
            . ($skipped ? ". {$skipped} baris bermasalah dilewati" : '')
            . '.';

        return redirect()->route('products.index')->with('success', $message);
    }

    /**
     * Unduh baris yang bermasalah sebagai CSV: kolom sama seperti template + kolom "Alasan".
     * Perbaiki di Excel, lalu unggah lagi file itu (kolom "Alasan" diabaikan saat import).
     */
    public function errors(Request $request)
    {
        $import = session('product_import');

        if (! $import || $request->query('token') !== $import['token'] || ! Storage::disk('local')->exists($import['path'])) {
            return redirect()
                ->route('products.import')
                ->withErrors(['file' => 'Sesi import sudah berakhir. Unggah ulang filenya.']);
        }

        try {
            $result = $this->analyze($import['path'], $import['extension']);
        } catch (RuntimeException $e) {
            return redirect()->route('products.import')->withErrors(['file' => $e->getMessage()]);
        }

        $fields = array_keys(ProductImport::LABELS);
        $numeric = ['price', 'cost_price', 'stock'];
        $filename = 'baris-bermasalah-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($result, $fields, $numeric) {
            $out = fopen('php://output', 'wb');

            // BOM + titik koma → langsung terbuka rapi di Excel berbahasa Indonesia
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(array_values(ProductImport::LABELS), ['Alasan', 'Baris Asal']), ';', '"', '');

            foreach ($result['items'] as $item) {
                if ($item['state'] !== 'error') {
                    continue;
                }

                $row = [];
                foreach ($fields as $field) {
                    $value = $item['raw'][$field] ?? '';

                    // angka yang sudah benar ditulis polos (52000 / 2,5); yang salah dibiarkan apa adanya
                    if (in_array($field, $numeric, true)) {
                        $parsed = ProductImport::number($value);
                        if (is_float($parsed)) {
                            $value = rtrim(rtrim(number_format($parsed, 2, ',', ''), '0'), ',');
                        }
                    }

                    // barcode angka panjang: tulis ="..." supaya Excel tidak mengubahnya
                    // jadi 4,00638E+12 atau membuang angka 0 di depan
                    if ($field === 'barcode' && preg_match('/^\d{8,}$/', $value)) {
                        $value = '="' . $value . '"';
                    } elseif ($value !== '' && in_array($value[0], ['=', '+', '@'], true)) {
                        // cegah sel dijalankan sebagai rumus saat dibuka di Excel
                        $value = "'" . $value;
                    }

                    $row[] = $value;
                }

                $row[] = implode(' ', $item['errors']);
                $row[] = $item['line'];

                fputcsv($out, $row, ';', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ============================================================
    // HELPER
    // ============================================================

    private function analyze(string $path, string $extension): array
    {
        $rows = SpreadsheetReader::read(Storage::disk('local')->path($path), $extension);

        // nama produk yang sudah ada → id (kalau ada nama kembar, yang paling lama dipakai)
        // kode & barcode yang sudah ada → id (keduanya unik, jadi tidak mungkin kembar)
        $existing = [];
        $existingCodes = [];
        $existingBarcodes = [];
        foreach (Tenant::table('products')->orderByDesc('id')->get(['id', 'name', 'code', 'barcode']) as $product) {
            $existing[mb_strtolower(trim(preg_replace('/\s+/u', ' ', $product->name)))] = $product->id;

            if ($product->code !== null && trim($product->code) !== '') {
                $existingCodes[mb_strtolower(trim(preg_replace('/\s+/u', ' ', $product->code)))] = $product->id;
            }

            if ($product->barcode !== null && trim($product->barcode) !== '') {
                $existingBarcodes[trim($product->barcode)] = $product->id;
            }
        }

        $result = ProductImport::analyze($rows, $existing, $existingCodes, $existingBarcodes);

        // kategori yang akan dibuat baru + yang namanya mirip kategori lama
        $result['categories'] = $result['ok']
            ? ProductImport::newCategories($result['items'], Tenant::table('categories')->pluck('name', 'id')->all())
            : [];

        return $result;
    }

    /** Buang file unggahan yang ditinggal (lebih dari 1 hari) */
    private function cleanOldFiles(): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files(self::DIR) as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }
}
