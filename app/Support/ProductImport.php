<?php

namespace App\Support;

/**
 * Memeriksa isi file import produk baris demi baris (belum menyentuh database).
 * Dipakai dua kali: saat PRATINJAU dan sekali lagi saat benar-benar DISIMPAN,
 * sehingga yang disimpan selalu sama dengan yang diperiksa.
 */
class ProductImport
{
    /** Satuan yang dikenal form produk */
    public const UNITS = ['PCS', 'SET', 'PASANG', 'BOTOL', 'LITER', 'GALON', 'PAK', 'METER'];

    /** Judul kolom yang dikenali (huruf besar/kecil & spasi diabaikan) */
    private const HEADERS = [
        'code' => ['kodeproduk', 'kode', 'kodebarang', 'kodepart', 'partnumber', 'partno', 'sku', 'code', 'productcode'],
        'barcode' => ['barcode', 'barkode', 'kodebarcode', 'barcodekemasan', 'ean', 'ean13', 'upc', 'gtin'],
        'name' => ['namaproduk', 'nama', 'produk', 'namabarang', 'barang', 'name', 'productname'],
        'category' => ['kategori', 'category', 'namakategori'],
        'price' => ['hargajual', 'harga', 'price', 'sellprice'],
        'cost_price' => ['hargabeli', 'hargamodal', 'modal', 'hpp', 'costprice', 'cost'],
        'stock' => ['stok', 'stock', 'jumlah', 'qty', 'stokawal'],
        'base_unit' => ['satuan', 'unit', 'baseunit'],
        'description' => ['deskripsi', 'keterangan', 'description'],
        'status' => ['status', 'aktif'],
        'is_favorite' => ['terlaris', 'favorit', 'favorite', 'isfavorite'],
    ];

    public const LABELS = [
        'code' => 'Kode Produk',
        'barcode' => 'Barcode',
        'name' => 'Nama Produk',
        'category' => 'Kategori',
        'price' => 'Harga Jual',
        'cost_price' => 'Harga Beli',
        'stock' => 'Stok',
        'base_unit' => 'Satuan',
        'description' => 'Deskripsi',
        'status' => 'Status',
        'is_favorite' => 'Terlaris',
    ];

    private const REQUIRED = ['name', 'category', 'price'];

    /**
     * @param  array  $rows      hasil SpreadsheetReader::read()
     * @param  array  $existing  nama produk yang sudah ada: [ 'nama huruf kecil' => id ]
     * @param  array  $existingCodes  kode produk yang sudah ada: [ 'kode huruf kecil' => id ]
     * @param  array  $existingBarcodes  barcode yang sudah ada: [ 'barcode' => id ] (persis, beda huruf = beda)
     * @return array{ok: bool, error: ?string, columns: array, items: array, counts: array}
     */
    public static function analyze(array $rows, array $existing, array $existingCodes = [], array $existingBarcodes = []): array
    {
        $result = [
            'ok' => false,
            'error' => null,
            'columns' => [],
            'items' => [],
            'counts' => ['new' => 0, 'update' => 0, 'error' => 0],
        ];

        // ---------- cari baris judul (boleh ada baris kosong / judul di atasnya) ----------
        $headerAt = null;
        $map = [];

        foreach (array_slice($rows, 0, 6, true) as $i => $row) {
            $candidate = self::mapHeaders($row);
            if (isset($candidate['name'])) {
                $headerAt = $i;
                $map = $candidate;
                break;
            }
        }

        if ($headerAt === null) {
            $result['error'] = 'Baris judul kolom tidak ditemukan. Baris pertama harus berisi judul seperti "Nama Produk", "Kategori", "Harga Jual". Gunakan template yang disediakan.';
            return $result;
        }

        $missing = array_diff(self::REQUIRED, array_keys($map));
        if ($missing) {
            $names = implode(', ', array_map(fn ($f) => '"' . self::LABELS[$f] . '"', $missing));
            $result['error'] = "Kolom wajib tidak ada di file: {$names}.";
            return $result;
        }

        $result['columns'] = array_keys($map);

        // ---------- periksa tiap baris ----------
        $seen = [];
        $seenCodes = [];
        $seenBarcodes = [];
        $seenIds = [];

        foreach ($rows as $i => $row) {
            if ($i <= $headerAt) {
                continue;
            }

            $get = fn (string $field) => isset($map[$field]) ? trim((string) ($row[$map[$field]] ?? '')) : '';
            $errors = [];
            $line = $i + 1; // nomor baris seperti di Excel (kurang-lebih; baris kosong sudah dibuang)

            $name = preg_replace('/\s+/u', ' ', $get('name'));
            $category = preg_replace('/\s+/u', ' ', $get('category'));
            $key = mb_strtolower($name);
            $code = preg_replace('/\s+/u', ' ', self::unwrapText($get('code')));
            $codeKey = mb_strtolower($code);
            $barcodeRaw = $get('barcode');
            $barcode = self::barcode($barcodeRaw);

            if ($name === '') {
                $errors[] = 'Nama produk kosong.';
            } elseif (mb_strlen($name) > 255) {
                $errors[] = 'Nama produk lebih dari 255 karakter.';
            } elseif (isset($seen[$key])) {
                $errors[] = "Nama sama dengan baris {$seen[$key]} di file ini.";
            }

            if ($code !== '') {
                if (mb_strlen($code) > 50) {
                    $errors[] = 'Kode produk lebih dari 50 karakter.';
                } elseif (isset($seenCodes[$codeKey])) {
                    $errors[] = "Kode produk sama dengan baris {$seenCodes[$codeKey]} di file ini.";
                }
            }

            if ($barcodeRaw !== '') {
                if ($barcode === null) {
                    $errors[] = 'Barcode "' . $barcodeRaw . '" terbaca sebagai angka ilmiah atau rusak oleh Excel. '
                        . 'Ubah format kolom Barcode menjadi Teks lalu ketik ulang.';
                } elseif (strlen($barcode) > 64) {
                    $errors[] = 'Barcode lebih dari 64 karakter.';
                } elseif (preg_match('/^[\x20-\x7E]+$/', $barcode) !== 1) {
                    $errors[] = 'Barcode hanya boleh berisi huruf, angka, dan tanda baca biasa.';
                } elseif (isset($seenBarcodes[$barcode])) {
                    $errors[] = "Barcode sama dengan baris {$seenBarcodes[$barcode]} di file ini.";
                } elseif (Barcode::isInternal($barcode) && ! isset($existingBarcodes[$barcode])) {
                    // kode toko hanya dibuat sistem, supaya tidak bentrok dengan produk yang dibuat nanti
                    $errors[] = 'Barcode berawalan 20 adalah kode toko yang dibuat otomatis. Kosongkan kolom ini.';
                }
            }

            if ($category === '') {
                $errors[] = 'Kategori kosong.';
            } elseif (mb_strlen($category) > 255) {
                $errors[] = 'Nama kategori terlalu panjang.';
            }

            $price = self::number($get('price'));
            if ($price === null) {
                $errors[] = 'Harga jual kosong.';
            } elseif ($price === false || $price < 0) {
                $errors[] = 'Harga jual bukan angka yang benar: "' . $get('price') . '".';
            } elseif ($price > 99999999.99) {
                $errors[] = 'Harga jual terlalu besar (maksimal Rp 99.999.999).';
            }

            $cost = self::number($get('cost_price'));
            if ($cost === false || ($cost !== null && $cost < 0)) {
                $errors[] = 'Harga beli bukan angka yang benar: "' . $get('cost_price') . '".';
            }

            $stock = self::number($get('stock'));
            if ($stock === false || ($stock !== null && $stock < 0)) {
                $errors[] = 'Stok bukan angka yang benar: "' . $get('stock') . '".';
            } elseif ($stock !== null && $stock > 99999999.99) {
                $errors[] = 'Stok terlalu besar.';
            }

            $unit = mb_strtoupper($get('base_unit'));
            if ($unit !== '' && mb_strlen($unit) > 10) {
                $errors[] = 'Satuan maksimal 10 huruf: "' . $unit . '".';
            }

            $status = self::yesNo($get('status'), ['aktif', 'active', 'tampil'], ['nonaktif', 'tidak aktif', 'inactive', 'sembunyi']);
            if ($status === -1) {
                $errors[] = 'Status harus "Aktif" atau "Nonaktif".';
            }

            $favorite = self::yesNo($get('is_favorite'), ['terlaris'], []);
            if ($favorite === -1) {
                $errors[] = 'Terlaris harus "Ya" atau "Tidak".';
            }

            if ($name !== '' && ! isset($seen[$key])) {
                $seen[$key] = $line;
            }

            if ($code !== '' && ! isset($seenCodes[$codeKey])) {
                $seenCodes[$codeKey] = $line;
            }

            if ($barcode !== null && $barcode !== '' && ! isset($seenBarcodes[$barcode])) {
                $seenBarcodes[$barcode] = $line;
            }

            // Produk lama dicari lewat KODE, lalu BARCODE, lalu NAMA.
            $idByCode = $code !== '' ? ($existingCodes[$codeKey] ?? null) : null;
            $idByBarcode = $barcode ? ($existingBarcodes[$barcode] ?? null) : null;
            $idByName = $existing[$key] ?? null;

            if ($idByCode && $idByBarcode && $idByCode != $idByBarcode) {
                $errors[] = 'Kode "' . $code . '" dan barcode "' . $barcode . '" milik dua produk yang berbeda di aplikasi.';
            }

            $idStrong = $idByCode ?? $idByBarcode;

            if ($idStrong && $idByName && $idStrong != $idByName) {
                $errors[] = $idByCode
                    ? 'Kode "' . $code . '" dan nama ini milik dua produk yang berbeda di aplikasi.'
                    : 'Barcode "' . $barcode . '" sudah dipakai produk lain, bukan produk dengan nama ini.';
            }

            $existingId = $idStrong ?? $idByName;
            $matchedBy = $idByCode ? 'code' : ($idByBarcode ? 'barcode' : ($idByName ? 'name' : null));

            // dua baris tidak boleh memperbarui produk yang sama
            if (! $errors && $existingId) {
                if (isset($seenIds[$existingId])) {
                    $errors[] = "Produk yang sama sudah diisi di baris {$seenIds[$existingId]} file ini.";
                } else {
                    $seenIds[$existingId] = $line;
                }
            }

            $state = $errors ? 'error' : ($existingId ? 'update' : 'new');
            $result['counts'][$state]++;

            $result['items'][] = [
                'line' => $line,
                'state' => $state,
                'errors' => $errors,
                'product_id' => $existingId,
                'matched_by' => $matchedBy, // 'code' · 'name' · null (produk baru)
                // isi asli tiap kolom (apa adanya dari file) — dipakai untuk unduhan "baris bermasalah"
                'raw' => array_map($get, array_combine(array_keys(self::LABELS), array_keys(self::LABELS))),
                'data' => [
                    'code' => $code !== '' ? $code : null,
                    'barcode' => $barcode !== null && $barcode !== '' ? $barcode : null,
                    'name' => $name,
                    'category' => $category,
                    'price' => is_float($price) ? round($price, 2) : null,
                    'cost_price' => is_float($cost) ? round($cost, 2) : null,
                    'stock' => is_float($stock) ? round($stock, 2) : null,
                    'base_unit' => $unit !== '' ? $unit : null,
                    'description' => $get('description') !== '' ? $get('description') : null,
                    'status' => $status === 1 || $status === 0 ? $status : null,
                    'is_favorite' => $favorite === 1 || $favorite === 0 ? $favorite : null,
                ],
            ];
        }

        if (! $result['items']) {
            $result['error'] = 'File tidak berisi data produk. Isi mulai dari baris di bawah judul kolom.';
            return $result;
        }

        $result['ok'] = true;

        return $result;
    }

    /**
     * Kategori di file yang BELUM ada di aplikasi (hanya dari baris yang valid),
     * beserta kategori lama yang namanya mirip — kemungkinan salah ketik.
     *
     * @param  array  $items     hasil analyze()['items']
     * @param  array  $existing  nama kategori yang sudah ada: [ id => 'Nama' ]
     * @return array<int, array{key: string, name: string, count: int, similar: ?array}>
     */
    public static function newCategories(array $items, array $existing): array
    {
        $known = [];
        foreach ($existing as $id => $name) {
            $known[mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)))] = ['id' => $id, 'name' => $name];
        }

        $new = [];
        foreach ($items as $item) {
            if ($item['state'] === 'error') {
                continue;
            }

            $name = $item['data']['category'];
            $key = mb_strtolower($name);

            if (isset($known[$key])) {
                continue;
            }

            $new[$key] ??= ['key' => $key, 'name' => $name, 'count' => 0, 'similar' => null];
            $new[$key]['count']++;
        }

        foreach ($new as $key => $category) {
            $best = null;
            $bestScore = 0;

            foreach ($known as $candidate) {
                $score = self::similarity($category['name'], $candidate['name']);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $candidate;
                }
            }

            if ($best && $bestScore >= 0.8) {
                $new[$key]['similar'] = $best;
            }
        }

        return array_values($new);
    }

    /**
     * Seberapa mirip dua nama kategori (0–1).
     * "&" dianggap "dan", tanda baca & spasi diabaikan:
     *   "Oli dan Pelumas" vs "Oli & Pelumas" → 1.0
     *   "Kampas Rm"       vs "Kampas Rem"    → ± 0.9
     */
    public static function similarity(string $a, string $b): float
    {
        $clean = function (string $text) {
            $text = mb_strtolower($text);
            $text = str_replace(['&', '+'], ' dan ', $text);
            return preg_replace('/[^a-z0-9]/', '', $text);
        };

        $a = $clean($a);
        $b = $clean($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        $longest = max(strlen($a), strlen($b));
        if ($longest > 255) {
            return 0.0; // batas levenshtein()
        }

        return 1 - levenshtein($a, $b) / $longest;
    }

    /** Cocokkan judul kolom di file dengan kolom yang dikenali → [ 'name' => 0, 'price' => 2, ... ] */
    private static function mapHeaders(array $row): array
    {
        $map = [];

        foreach ($row as $index => $title) {
            // "Harga Jual (Rp) *" → "hargajual"
            $clean = preg_replace('/\(.*?\)/u', '', mb_strtolower((string) $title));
            $clean = preg_replace('/[^a-z0-9]/', '', $clean);

            foreach (self::HEADERS as $field => $aliases) {
                if (! isset($map[$field]) && in_array($clean, $aliases, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Angka dari Excel / ketikan Indonesia.
     *   "52000" · "52.000" · "Rp 52.000" · "1.250.000,50" · "2,5" → float
     * @return float|null|false  null = kosong · false = bukan angka
     */
    public static function number(string $value): float|null|false
    {
        $value = trim(str_replace(["\u{00A0}", ' '], '', $value));
        $value = preg_replace('/^rp\.?/i', '', $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        // 1.250.000 atau 1.250.000,50 → titik = pemisah ribuan
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $value)) {
            return (float) str_replace(',', '.', str_replace('.', '', $value));
        }

        // 1,250,000 atau 1,250,000.50 → koma = pemisah ribuan
        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $value)) {
            return (float) str_replace(',', '', $value);
        }

        // 2,5 → koma = desimal
        if (preg_match('/^-?\d+,\d+$/', $value)) {
            return (float) str_replace(',', '.', $value);
        }

        // 52000 · 52000.5 · 5.2E+4 (angka mentah dari Excel)
        if (is_numeric($value)) {
            return (float) $value;
        }

        return false;
    }

    /**
     * Barcode dari sel Excel / CSV.
     *   "4006381333931" · "4006381333931.0" · ="4006381333931" → "4006381333931"
     *   "4.00638E+12" (angka ilmiah, digit sudah hilang) → null
     * @return string|null  '' = kosong · null = rusak
     */
    public static function barcode(string $value): ?string
    {
        $value = trim(self::unwrapText($value));

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d+(\.\d+)?e[+-]?\d+$/i', $value)) {
            return null;
        }

        // angka dari Excel kadang ditulis "4006381333931.0"
        if (preg_match('/^(\d+)\.0+$/', $value, $m)) {
            return $m[1];
        }

        return $value;
    }

    /** ="00123" (cara CSV menjaga teks di Excel) → 00123 */
    private static function unwrapText(string $value): string
    {
        return preg_match('/^="(.*)"$/s', trim($value), $m) ? $m[1] : $value;
    }

    /** @return int|null  1 = ya · 0 = tidak · null = kosong · -1 = tidak dikenali */
    private static function yesNo(string $value, array $extraYes, array $extraNo): ?int
    {
        $value = mb_strtolower(trim($value));

        if ($value === '') {
            return null;
        }
        if (in_array($value, array_merge(['ya', 'y', 'yes', '1', 'true', 'x'], $extraYes), true)) {
            return 1;
        }
        if (in_array($value, array_merge(['tidak', 'tdk', 't', 'no', 'n', '0', 'false', '-'], $extraNo), true)) {
            return 0;
        }

        return -1;
    }
}
