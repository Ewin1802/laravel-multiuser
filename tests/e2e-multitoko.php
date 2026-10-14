<?php
/**
 * UJI END-TO-END MULTI-TOKO
 *
 *   DB_CONNECTION=sqlite DB_DATABASE=database/uji.sqlite php tests/e2e-multitoko.php
 *
 * ⚠ Menjalankan migrate:fresh — SEMUA DATA di database yang dipakai DIHAPUS.
 *   Skrip menolak jalan kecuali koneksinya sqlite. Jangan arahkan ke database produksi.
 *
 * Butuh LICENSE_PRIVATE_KEY / LICENSE_PUBLIC_KEY di .env, dan route bernama
 * login / logout / register (dari Fortify).
 */
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$console = $app->make(Illuminate\Contracts\Console\Kernel::class);
$console->bootstrap();

if (config('database.default') !== 'sqlite') {
    fwrite(STDERR, "Ditolak: uji ini menghapus database. Jalankan dengan DB_CONNECTION=sqlite.\n");
    exit(2);
}

use App\Models\Order;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Store;
use App\Models\User;
use App\Support\License;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

// fungsi MySQL yang dipakai laporan → padanan sqlite (hanya untuk uji)
function sqliteMysqlPolyfill() {
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
    $pdo->sqliteCreateFunction('CURDATE', fn () => date('Y-m-d'), 0);
    $pdo->sqliteCreateFunction('STR_TO_DATE', fn ($v, $f) => $v ? str_replace('T', ' ', substr($v, 0, 19)) : null, 2);
    $pdo->sqliteCreateFunction('SUBSTRING_INDEX', function ($s, $d, $n) { $p = explode($d, (string) $s); return $n > 0 ? implode($d, array_slice($p, 0, $n)) : implode($d, array_slice($p, $n)); }, 3);
    $pdo->sqliteCreateFunction('YEAR', fn ($v) => $v ? (int) substr($v, 0, 4) : null, 1);
    $pdo->sqliteCreateFunction('MONTH', fn ($v) => $v ? (int) substr($v, 5, 2) : null, 1);
    $pdo->sqliteCreateFunction('LEAST', fn (...$a) => min($a), -1);
}
$console->call('migrate:fresh', ['--seed' => true, '--force' => true]);
$console->call('db:seed', ['--class' => 'DemoStoreSeeder', '--force' => true]);
sqliteMysqlPolyfill();
function errs() { $e = session('errors'); if (! $e) return []; return is_array($e) ? $e : $e->all(); }

$fail = 0;
$pass = 0;
function ok($cond, $label, $extra = null)
{
    global $fail, $pass;
    if ($cond) { $pass++; echo "  ✔ $label\n"; return; }
    $fail++;
    echo "  ✘ $label" . ($extra !== null ? '  → ' . (is_string($extra) ? $extra : json_encode($extra)) : '') . "\n";
}
function section($t) { echo "\n== $t\n"; }

/** Request API (JSON) lewat kernel. */
function api($method, $uri, $data = [], $token = null, $device = null, $files = [])
{
    global $kernel, $app;
    Auth::forgetGuards();
    Tenant::forget();
    $server = ['HTTP_ACCEPT' => 'application/json'];
    if ($token) $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    if ($device) $server['HTTP_X_DEVICE_ID'] = $device;
    if ($files) {
        $req = Request::create('/api' . $uri, $method, $data, [], $files, $server);
    } else {
        $server['CONTENT_TYPE'] = 'application/json';
        $req = Request::create('/api' . $uri, $method, [], [], [], $server, json_encode($data));
    }
    $res = $kernel->handle($req);
    $kernel->terminate($req, $res);
    $json = json_decode($res->getContent(), true);
    return [$res->getStatusCode(), $json, $res->getContent()];
}

/** Request web sebagai user tertentu. */
function web($method, $uri, $user, $data = [])
{
    global $kernel;
    Auth::forgetGuards();
    Tenant::forget();
    $req = Request::create($uri, $method, $data);
    $app = app();
    $app['auth']->guard('web')->setUser($user);
    $res = $kernel->handle($req);
    $kernel->terminate($req, $res);
    return [$res->getStatusCode(), $res->getContent(), $res];
}

$pk = base64_decode(config('license.public_key'));
$superadmin = User::where('role', 'superadmin')->first();

// =====================================================================
section('Pendaftaran & persetujuan');
[$st, $j] = api('POST', '/register', [
    'store_name' => 'Toko Baru', 'name' => 'Budi', 'email' => 'budi@x.id', 'phone_number' => '0812000111',
    'password' => 'rahasia1', 'password_confirmation' => 'rahasia1', 'device_id' => 'DEV-BUDI-1',
]);
ok($st === 201 && $j['code'] === 'store_pending', 'daftar via app → pending', [$st, $j]);
[$st, $j] = api('POST', '/login', ['email' => 'budi@x.id', 'password' => 'rahasia1', 'device_id' => 'DEV-BUDI-1']);
ok($st === 403 && $j['code'] === 'store_pending', 'login sebelum disetujui ditolak', [$st, $j]);
$budiStore = User::where('email', 'budi@x.id')->first()->store;
ok($budiStore->trial_started_at === null, 'trial belum mulai sebelum disetujui');

app(App\Services\StoreManager::class)->approve($budiStore, $superadmin);
$budiStore->refresh();
ok($budiStore->trial_ends_at && abs($budiStore->trial_ends_at->diffInHours(now()->addDays(3))) < 1, 'trial 3 hari sejak disetujui');
ok(User::where('email', 'budi@x.id')->value('role') === 'admin', 'role naik ke admin');

$trialEnd = $budiStore->trial_ends_at->copy();
app(App\Services\StoreManager::class)->approve($budiStore->fresh(), $superadmin);
ok($budiStore->fresh()->trial_ends_at->eq($trialEnd), 'setujui ulang tidak mengulang trial');

// =====================================================================
section('Login kasir & lisensi');
[$st, $j] = api('POST', '/login', ['email' => 'demo@toko.test', 'password' => 'password123', 'device_id' => 'HP-A', 'device_name' => 'HP A', 'platform' => 'android', 'app_version' => '2.0.0']);
ok($st === 200, 'login demo', [$st, $j]);
$tokenA = $j['data']['token'] ?? null;
ok(($j['data']['device']['cashier_status'] ?? null) === 'this_device', 'HP pertama otomatis jadi kasir');
$lic = License::verify($j['data']['license']['token'] ?? '', $pk);
ok($lic !== null, 'tanda tangan lisensi valid dengan kunci publik');
ok(($lic['state'] ?? null) === 'trial' && $lic['can_transact'] === true && $lic['device_id'] === 'HP-A', 'isi lisensi: trial, boleh transaksi, terikat HP-A', $lic);
ok(($lic['offline_limit_ms'] ?? 0) === 48 * 3600 * 1000, 'batas offline 48 jam');
$tampered = $j['data']['license']['token'];
$parts = explode('.', $tampered);
$payload = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
$payload['transact_until'] += 86400000 * 365;
$forged = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=') . '.' . $parts[1];
ok(License::verify($forged, $pk) === null, 'lisensi yang diubah ditolak');

[$st] = api('GET', '/products', [], $tokenA, null);
ok($st === 426, 'request tanpa X-Device-Id ditolak (426)', $st);

// =====================================================================
section('Produk untuk HP (satuan + batch)');
[$st, $j] = api('GET', '/products', [], $tokenA, 'HP-A');
ok($st === 200, 'GET /products');
$rokok = collect($j['data'])->firstWhere('name', 'Rokok Contoh 16');
$susu = collect($j['data'])->firstWhere('name', 'Susu UHT Contoh 200ml');
ok(count($rokok['units']) === 3, 'rokok punya 3 satuan', $rokok['units']);
ok($rokok['stock_text'] === '5 SLOP', 'stok terurai "5 SLOP"', $rokok['stock_text']);
ok(count($susu['batches']) === 2 && $susu['batches'][0]['remaining_quantity'] == 48, 'susu 2 batch, FEFO pertama sisa 48', $susu['batches']);
$bungkus = collect($rokok['units'])->firstWhere('name', 'BUNGKUS');
$slop = collect($rokok['units'])->firstWhere('name', 'SLOP');
$batch1 = $susu['batches'][0]['id'];

// =====================================================================
section('Transaksi offline: multi-satuan + FEFO');
$oid = (string) Str::uuid();
$order = [
    'client_order_id' => $oid, 'payment_amount' => 500000, 'sub_total' => 0, 'tax' => 0, 'discount' => 0,
    'discount_amount' => 0, 'service_charge' => 0, 'total' => 0, 'payment_method' => 'Cash', 'total_item' => 4,
    'id_kasir' => 2, 'nama_kasir' => 'Demo', 'transaction_time' => now()->subHour()->format('Y-m-d\TH:i:s'),
    'device_elapsed_ms' => 123456,
    'order_items' => [
        ['id_product' => $rokok['id'], 'product_unit_id' => $slop['id'], 'unit_name' => 'SLOP', 'unit_conversion' => 160, 'quantity' => 1, 'price' => 310000],
        ['id_product' => $rokok['id'], 'product_unit_id' => $bungkus['id'], 'unit_name' => 'BUNGKUS', 'unit_conversion' => 16, 'quantity' => 2, 'price' => 32000],
        ['id_product' => $rokok['id'], 'quantity' => 3, 'price' => 2500],
        // HP sudah membagi batch: 48 dari batch1, 2 dari batch2
        ['id_product' => $susu['id'], 'quantity' => 50, 'price' => 6000,
            'batches' => [['batch_id' => $batch1, 'quantity' => 48], ['batch_id' => $susu['batches'][1]['id'], 'quantity' => 2]]],
    ],
];
[$st, $j] = api('POST', '/save-order', $order, $tokenA, 'HP-A');
ok($st === 200 && $j['status'] === 'success', 'order tersimpan', [$st, $j]);
Tenant::set(1);
$p1 = Product::find($rokok['id']);
ok((float) $p1->stock === 800.0 - 160 - 32 - 3, 'stok rokok 605 batang', $p1->stock);
ok($p1->load('units')->stockBreakdown() === '3 SLOP, 7 BUNGKUS, 13 BATANG', 'tampilan "3 SLOP, 7 BUNGKUS, 13 BATANG"', $p1->stockBreakdown());
$b1 = StockBatch::find($batch1);
ok($b1->status === 'depleted' && (float) $b1->remaining_quantity === 0.0, 'batch terdekat habis (FEFO)');
$o = Order::where('client_order_id', $oid)->with('orderItems.batches')->first();
$slopItem = $o->orderItems->firstWhere('unit_name', 'SLOP');
ok($slopItem && (float) $slopItem->base_quantity === 160.0 && abs($slopItem->cost_total - 290000) < 0.01, 'baris SLOP: 160 batang, modal 290.000', $slopItem?->toArray());
$susuItem = $o->orderItems->firstWhere('product_id', $susu['id']);
ok(abs($susuItem->cost_total - (48 * 5000 + 2 * 4916.6667)) < 0.05, 'modal susu dari 2 batch', $susuItem->cost_total);
ok(! $o->stock_conflict && ! $o->clock_suspect && ! $o->from_inactive_device, 'tidak ada tanda', $o->only('stock_conflict', 'clock_suspect', 'sync_notes'));
Tenant::forget();

[$st, $j] = api('POST', '/save-order', $order, $tokenA, 'HP-A');
ok($st === 200 && $j['status'] === 'exists', 'kirim ulang → exists (tidak dobel)');

// konversi produk diubah → riwayat tetap pakai konversi lama
Tenant::run(1, function () use ($rokok) {
    App\Models\ProductUnit::where('product_id', $rokok['id'])->where('name', 'BUNGKUS')->update(['conversion' => 20]);
});
Tenant::run(1, fn () => ok((float) Order::where('client_order_id', $oid)->first()->orderItems->firstWhere('unit_name', 'BUNGKUS')->base_quantity === 32.0, 'konversi diubah → riwayat order tetap 32 batang'));
Tenant::run(1, function () use ($rokok) {
    App\Models\ProductUnit::where('product_id', $rokok['id'])->where('name', 'BUNGKUS')->update(['conversion' => 16]);
});

// =====================================================================
section('Stok server kurang → tetap diterima, ditandai');
$oid2 = (string) Str::uuid();
[$st, $j] = api('POST', '/save-order', array_merge($order, ['client_order_id' => $oid2, 'order_items' => [
    ['id_product' => $susu['id'], 'quantity' => 100, 'price' => 6000],
]]), $tokenA, 'HP-A');
ok($st === 200, 'order dengan stok kurang diterima', [$st, $j]);
Tenant::run(1, function () use ($oid2, $susu) {
    $o = Order::where('client_order_id', $oid2)->first();
    ok($o->stock_conflict, 'ditandai stock_conflict', $o->sync_notes);
    ok((float) Product::find($susu['id'])->stock === -30.0, 'stok susu boleh minus (-30)', Product::find($susu['id'])->stock);
});

// =====================================================================
section('Jam HP janggal');
[$st, $j] = api('POST', '/save-order', array_merge($order, ['client_order_id' => (string) Str::uuid(),
    'transaction_time' => now()->addDays(2)->format('Y-m-d\TH:i:s'),
    'order_items' => [['id_product' => $rokok['id'], 'quantity' => 1, 'price' => 2500]]]), $tokenA, 'HP-A');
ok($st === 200 && $j['data']['order']['clock_suspect'] === true, 'transaksi bertanggal masa depan ditandai', $j['data']['order']['sync_notes'] ?? $j);

[$st, $j] = api('POST', '/clock-events', ['events' => [
    ['client_uuid' => $cu = (string) Str::uuid(), 'kind' => 'clock_rollback', 'device_time' => '2026-10-01T10:00:00', 'trusted_time' => now()->toIso8601String(), 'drift_seconds' => -1100000],
]], $tokenA, 'HP-A');
ok($st === 200 && $j['data']['saved'] === 1, 'log jam mundur diterima', $j);
[$st, $j] = api('POST', '/clock-events', ['events' => [['client_uuid' => $cu, 'kind' => 'clock_rollback']]], $tokenA, 'HP-A');
ok($j['data']['saved'] === 0, 'log yang sama tidak dobel');

// =====================================================================
section('Zona waktu HP (WITA) vs server');
$wita = now()->setTimezone('Asia/Makassar')->format('Y-m-d\\TH:i:s');
[$st, $j] = api('POST', '/save-order', array_merge($order, ['client_order_id' => (string) Str::uuid(),
    'transaction_time' => $wita, 'tz_offset' => 480,
    'order_items' => [['id_product' => $rokok['id'], 'quantity' => 1, 'price' => 2500]]]), $tokenA, 'HP-A');
ok($st === 200 && $j['data']['order']['clock_suspect'] === false, 'jam lokal WITA + tz_offset → tidak ditandai', $j['data']['order']['sync_notes'] ?? $j);
if (config('app.timezone') === 'UTC') {
    [$st, $j] = api('POST', '/save-order', array_merge($order, ['client_order_id' => (string) Str::uuid(),
        'transaction_time' => $wita,
        'order_items' => [['id_product' => $rokok['id'], 'quantity' => 1, 'price' => 2500]]]), $tokenA, 'HP-A');
    ok($j['data']['order']['clock_suspect'] === true, 'tanpa tz_offset (aplikasi lama) → terbaca 8 jam di masa depan, ditandai');
}

// =====================================================================
section('Satu kasir aktif per toko');
[$st, $j] = api('POST', '/login', ['email' => 'demo@toko.test', 'password' => 'password123', 'device_id' => 'HP-B', 'device_name' => 'HP B']);
$tokenB = $j['data']['token'];
ok($j['data']['device']['cashier_status'] === 'other_device' && $j['data']['device']['active_device_name'] === 'HP A', 'HP kedua: kasir ada di HP A');
$licB = License::verify($j['data']['license']['token'], $pk);
ok($licB['is_cashier'] === false && $licB['can_transact'] === false, 'lisensi HP B: mode lihat');
[$st, $j] = api('POST', '/save-order', array_merge($order, ['client_order_id' => (string) Str::uuid(),
    'order_items' => [['id_product' => $rokok['id'], 'quantity' => 1, 'price' => 2500]]]), $tokenB, 'HP-B');
ok($st === 200 && $j['data']['order']['from_inactive_device'] === true, 'order dari HP non-kasir diterima tapi ditandai', $j['data']['order'] ?? $j);

[$st, $j] = api('POST', '/device/claim', ['confirm' => true], $tokenB, 'HP-B');
ok($st === 200 && $j['data']['device']['cashier_status'] === 'this_device', 'pindahkan kasir ke HP B');
[$st, $j] = api('GET', '/license', [], $tokenA, 'HP-A');
$licA = License::verify($j['data']['license']['token'], $pk);
ok($licA['is_cashier'] === false && $j['data']['device']['active_device_name'] === 'HP B', 'HP A sekarang mode lihat');
// order lama HP A (dibuat sebelum dipindah) tetap diterima tanpa tanda
[$st, $j] = api('POST', '/save-order', array_merge($order, ['client_order_id' => (string) Str::uuid(),
    'transaction_time' => now()->subHours(2)->format('Y-m-d\TH:i:s'),
    'order_items' => [['id_product' => $rokok['id'], 'quantity' => 1, 'price' => 2500]]]), $tokenA, 'HP-A');
ok($st === 200 && $j['data']['order']['from_inactive_device'] === false, 'order HP A dari sebelum pindah: diterima, tanpa tanda', $j['data']['order']['sync_notes'] ?? $j);

// =====================================================================
section('Barang keluar dari HP');
[$st, $j] = api('POST', '/stock-write-offs', ['items' => [
    ['client_uuid' => $wu = (string) Str::uuid(), 'product_id' => $rokok['id'], 'quantity' => 5, 'reason' => 'damaged', 'occurred_at' => now()->toIso8601String()],
]], $tokenB, 'HP-B');
ok($st === 200 && count($j['data']['done']) === 1, 'barang rusak tercatat', $j);
[$st, $j] = api('POST', '/stock-write-offs', ['items' => [['client_uuid' => $wu, 'product_id' => $rokok['id'], 'quantity' => 5, 'reason' => 'damaged']]], $tokenB, 'HP-B');
Tenant::run(1, fn () => ok(App\Models\StockWriteOff::count() === 1, 'kirim ulang tidak dobel'));

// =====================================================================
section('Isolasi antar toko');
[$st, $j] = api('POST', '/login', ['email' => 'budi@x.id', 'password' => 'rahasia1', 'device_id' => 'DEV-BUDI-1']);
$tokenBudi = $j['data']['token'];
[$st, $j] = api('GET', '/products', [], $tokenBudi, 'DEV-BUDI-1');
ok($st === 200 && count($j['data']) === 0, 'toko lain tidak melihat produk toko demo', count($j['data'] ?? []));
[$st, $j] = api('POST', '/products/edit', ['id' => $rokok['id'], 'name' => 'hack', 'price' => 1, 'category_id' => 1, 'base_unit' => 'PCS'], $tokenBudi, 'DEV-BUDI-1');
ok($st === 422, 'tidak bisa mengubah produk toko lain', [$st, $j['errors'] ?? $j]);
[$st, $j] = api('POST', '/save-order', array_merge($order, ['client_order_id' => (string) Str::uuid(),
    'order_items' => [['id_product' => $rokok['id'], 'quantity' => 1, 'price' => 1]]]), $tokenBudi, 'DEV-BUDI-1');
ok($st === 500 && str_contains($j['message'] ?? '', 'tidak ditemukan'), 'tidak bisa menjual produk toko lain', [$st, $j]);
[$st, $j] = api('POST', '/save-order', $order, $tokenBudi, 'DEV-BUDI-1');
ok($st === 409, 'client_order_id toko lain tidak dibocorkan', [$st, $j]);

// =====================================================================
section('Langganan habis → hanya-lihat');
Store::whereKey(1)->update(['trial_ends_at' => now()->subMinutes(30)]);
[$st, $j] = api('GET', '/license', [], $tokenB, 'HP-B');
$lic = License::verify($j['data']['license']['token'], $pk);
ok($lic['state'] === 'expired' && $lic['can_transact'] === false, 'trial habis → expired, tanpa tenggang', $lic['state']);
[$st, $j] = api('POST', '/products/status', ['id' => $rokok['id'], 'status' => 0], $tokenB, 'HP-B');
ok($st === 403 && $j['code'] === 'subscription_inactive', 'ubah produk ditolak saat habis', [$st, $j]);
[$st, $j] = api('GET', '/products', [], $tokenB, 'HP-B');
ok($st === 200, 'tetap bisa melihat data');
[$st, $j] = api('POST', '/save-order', array_merge($order, ['client_order_id' => (string) Str::uuid(),
    'transaction_time' => now()->subHours(3)->format('Y-m-d\TH:i:s'),
    'order_items' => [['id_product' => $rokok['id'], 'quantity' => 1, 'price' => 2500]]]), $tokenB, 'HP-B');
ok($st === 200 && $j['data']['order']['clock_suspect'] === false, 'transaksi lama (sebelum habis) tetap diterima tanpa tanda');
[$st, $j] = api('POST', '/save-order', array_merge($order, ['client_order_id' => (string) Str::uuid(),
    'transaction_time' => now()->subMinutes(5)->format('Y-m-d\TH:i:s'),
    'order_items' => [['id_product' => $rokok['id'], 'quantity' => 1, 'price' => 2500]]]), $tokenB, 'HP-B');
ok($st === 200 && $j['data']['order']['clock_suspect'] === true, 'transaksi setelah habis diterima tapi ditandai');

// =====================================================================
section('Pembayaran manual');
$img = tempnam(sys_get_temp_dir(), 'p') . '.png';
file_put_contents($img, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
$upload = new Illuminate\Http\UploadedFile($img, 'bukti.png', 'image/png', null, true);
[$st, $j] = api('POST', '/subscription/payments', ['plan_id' => 1, 'sender_name' => 'Demo', 'transferred_at' => now()->toDateString()], $tokenB, 'HP-B', ['proof' => $upload]);
ok($st === 201, 'kirim bukti transfer dari app (saat expired)', [$st, $j]);
$payment = App\Models\SubscriptionPayment::withoutGlobalScope('store')->latest('id')->first();
app(App\Services\StoreManager::class)->confirmPayment($payment, $superadmin);
$s1 = Store::find(1);
ok($s1->subscription()->state() === 'active' && abs($s1->paid_until->diffInHours(now()->addMonth())) < 1, 'dikonfirmasi → aktif 1 bulan dari sekarang', $s1->paid_until);

// bayar lagi saat masih aktif → menyambung dari paid_until
$prev = $s1->paid_until->copy();
app(App\Services\StoreManager::class)->recordAndConfirm($s1, App\Models\Plan::where('code', 'yearly')->first(), $superadmin);
ok($s1->fresh()->paid_until->eq($prev->copy()->addMonthsNoOverflow(12)), 'bayar saat aktif → menyambung 12 bulan dari tanggal berakhir');

// tenggang 7 hari setelah berbayar habis
Store::whereKey(1)->update(['paid_until' => now()->subDays(3)]);
$sub = Store::find(1)->subscription();
ok($sub->state() === 'grace' && $sub->canTransact(), 'berbayar telat 3 hari → masa tenggang, masih bisa transaksi');
Store::whereKey(1)->update(['paid_until' => now()->subDays(8)]);
ok(Store::find(1)->subscription()->state() === 'expired', 'lewat 7 hari → expired');
// bayar saat tenggang menyambung dari paid_until
Store::whereKey(1)->update(['paid_until' => now()->subDays(2)]);
[$ps, $pe] = Store::find(1)->subscription()->nextPeriod(App\Models\Plan::find(1));
ok($ps->eq(Store::find(1)->paid_until), 'bayar saat tenggang → periode mulai dari paid_until lama');
Store::whereKey(1)->update(['paid_until' => now()->addMonth()]);

// =====================================================================
section('Halaman web pemilik toko');
$demo = User::where('email', 'demo@toko.test')->first();
foreach (['/dashboard', '/products', '/products/create', '/products/1/edit', '/stock-ins', '/stock-ins/create', '/orders', '/expire', '/langganan', '/toko', '/receivables', '/stock-conversions', '/suppliers', '/expenses', '/categories', '/reports/supplier-prices'] as $uri) {
    [$st, $body] = web('GET', $uri, $demo);
    ok($st === 200, "GET $uri", $st === 200 ? null : $st . ' ' . substr(strip_tags($body), 0, 300));
}
[$st, $body] = web('GET', '/orders/' . Order::withoutGlobalScope('store')->where('client_order_id', $oid)->value('id') . '/edit', $demo);
ok($st === 200, 'GET edit order', $st === 200 ? null : substr(strip_tags($body), 0, 300));
[$st] = web('GET', '/superadmin', $demo);
ok($st === 403, 'pemilik toko tidak bisa buka panel superadmin', $st);

// stok masuk web: susu ber-expire, 1 DUS + tanggal expire
Tenant::set(1);
$dus = App\Models\ProductUnit::where('product_id', $susu['id'])->where('name', 'DUS')->first();
$before = (float) Product::find($susu['id'])->stock;
Tenant::forget();
[$st, $body, $res] = web('POST', '/stock-ins', $demo, [
    'supplier_id' => 1, 'received_at' => now()->toDateString(), 'payment_status' => 'paid',
    'items' => [['product_id' => $susu['id'], 'product_unit_id' => $dus->id, 'quantity' => 1, 'cost_price' => 120000, 'expiry_date' => now()->addMonths(6)->toDateString()]],
]);
ok($st === 302 && ! errs(), 'simpan stok masuk via web', errs());
Tenant::run(1, function () use ($susu, $before) {
    ok((float) Product::find($susu['id'])->stock === $before + 24, 'stok susu +24', Product::find($susu['id'])->stock);
    ok(StockBatch::where('product_id', $susu['id'])->count() === 3, 'batch baru dibuat');
});
[$st] = web('POST', '/stock-ins', $demo, [
    'supplier_id' => 1, 'received_at' => now()->toDateString(), 'payment_status' => 'paid',
    'items' => [['product_id' => $susu['id'], 'quantity' => 1, 'cost_price' => 5000]],
]);
ok(str_contains(json_encode(errs()), 'expire'), 'produk ber-expire tanpa tanggal ditolak', errs());

// produk baru via web dengan satuan
[$st] = web('POST', '/products', $demo, [
    'name' => 'Kopi Sachet', 'description' => '-', 'price' => 1500, 'category_id' => 2, 'stock' => 100,
    'status' => 1, 'is_favorite' => 0, 'base_unit' => 'sachet',
    'units' => [['name' => 'renceng', 'conversion' => 10, 'price' => 14000, 'barcode' => '8991111111111']],
]);
Tenant::run(1, function () {
    $k = Product::where('name', 'Kopi Sachet')->with('units')->first();
    ok($k && $k->units->count() === 2 && $k->base_unit === 'SACHET' && $k->stockBreakdown() === '10 RENCENG', 'produk + satuan dibuat via web', $k?->units->pluck('name'));
});

// ---- modal (harga beli) & cek produk mirip
[$st] = web('POST', '/products', $demo, [
    'name' => 'Gula Pasir 1 kg', 'description' => '-', 'price' => 18000, 'cost_price' => 15500, 'category_id' => 2,
    'stock' => 10, 'status' => 1, 'is_favorite' => 0, 'base_unit' => 'pcs',
]);
Tenant::run(1, function () {
    $g = Product::where('name', 'Gula Pasir 1 kg')->first();
    ok($g && (float) $g->cost_price === 15500.0, 'produk baru menyimpan harga beli', $g?->cost_price);
});
[$st] = web('POST', '/products', $demo, [
    'name' => 'gula pasir 1kg', 'description' => '-', 'price' => 18000, 'category_id' => 2,
    'stock' => 0, 'status' => 1, 'is_favorite' => 0, 'base_unit' => 'pcs',
]);
ok(str_contains(json_encode(errs()), 'sudah ada') && session('similar_products'), 'nama hampir sama ditolak tanpa konfirmasi', errs());
Tenant::run(1, fn () => ok(Product::where('name', 'like', 'gula pasir%')->count() === 1, 'produk dobel tidak terbuat'));
[$st] = web('POST', '/products', $demo, [
    'name' => 'gula pasir 1kg', 'description' => '-', 'price' => 18000, 'category_id' => 2, 'confirm_duplicate' => 1,
    'stock' => 0, 'status' => 1, 'is_favorite' => 0, 'base_unit' => 'pcs',
]);
Tenant::run(1, fn () => ok(Product::where('name', 'like', 'gula pasir%')->count() === 2, 'dengan konfirmasi "produk berbeda" tetap tersimpan'));
Tenant::run(1, function () {
    $dup = Product::where('name', 'gula pasir 1kg')->first();
    ok($dup->cost_price === null, 'harga beli kosong → null (belum ada modal)');
});
[$st, $body] = web('GET', '/products/similar?name=' . urlencode('Gula Psir 1 kg'), $demo);
$sim = json_decode($body, true);
ok($st === 200 && ($sim['data'][0]['score'] ?? 0) >= 0.85, 'endpoint produk mirip (salah ketik)', $sim['data'][0] ?? $body);
[$st, $body] = web('GET', '/products/similar?name=' . urlencode('Pulsa Telkomsel'), $demo);
ok($st === 200 && json_decode($body, true)['data'] === [], 'nama tidak mirip → kosong');
ok(App\Support\ProductMatcher::score(App\Support\ProductMatcher::prepare('Air Mineral 600 ml'), App\Support\ProductMatcher::prepare('Air Mineral Aqua 600ml')) >= 0.6, 'kata sama dikenali mirip');

// edit: isi modal → batch tanpa modal ikut terisi
Tenant::set(1);
$sp = Product::find($susu['id']);
StockBatch::where('product_id', $sp->id)->where('status', 'active')->limit(1)->update(['cost_price' => 0]);
$zeroBefore = StockBatch::where('product_id', $sp->id)->where('status', 'active')->where('cost_price', '<=', 0)->count();
Tenant::forget();
[$st] = web('PUT', '/products/' . $sp->id, $demo, [
    'name' => $sp->name, 'description' => $sp->description ?: '-', 'price' => $sp->price, 'cost_price' => 4800,
    'category_id' => $sp->category_id, 'stock' => $sp->stock, 'status' => 1, 'is_favorite' => 0, 'base_unit' => $sp->base_unit,
    'track_expiry' => 1, 'expiry_warning_days' => 30,
    'units' => $sp->units()->where('is_base', false)->get()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'conversion' => $u->conversion, 'price' => $u->price, 'barcode' => $u->barcode])->all(),
]);
Tenant::run(1, function () use ($sp, $zeroBefore) {
    ok($zeroBefore > 0 && StockBatch::where('product_id', $sp->id)->where('status', 'active')->where('cost_price', '<=', 0)->count() === 0
        && (float) Product::find($sp->id)->cost_price === 4800.0, 'isi modal di edit → batch tanpa modal ikut terisi', errs());
});

// hapus order → stok & batch kembali
Tenant::set(1);
$ord = Order::where('client_order_id', $oid)->first();
$stockBefore = (float) Product::find($susu['id'])->stock;
Tenant::forget();
[$st] = web('DELETE', '/orders/' . $ord->id, $demo);
Tenant::run(1, function () use ($susu, $stockBefore, $batch1) {
    ok((float) Product::find($susu['id'])->stock === $stockBefore + 50, 'hapus order → stok susu kembali 50', Product::find($susu['id'])->stock);
    $b = StockBatch::find($batch1);
    ok($b->status === 'active' && (float) $b->remaining_quantity === 48.0, 'batch terdekat aktif lagi (48)');
});

// barang kedaluwarsa → rusak
Tenant::run(1, fn () => StockBatch::whereKey($batch1)->update(['expiry_date' => now()->subDay()->toDateString()]));
[$st, $body] = web('GET', '/expire', $demo);
ok($st === 200 && str_contains($body, 'Sudah kedaluwarsa'), 'halaman expire');
[$st] = web('POST', "/expire/batch/$batch1/keluarkan", $demo, ['reason' => 'returned']);
Tenant::run(1, function () use ($batch1) {
    ok(StockBatch::find($batch1)->status === 'written_off', 'batch kedaluwarsa diretur');
});
// FEFO melewati batch kedaluwarsa
Tenant::run(1, fn () => StockBatch::whereKey($batch1)->update(['status' => 'active', 'remaining_quantity' => 10]));
[$st, $j] = api('POST', '/save-order', array_merge($order, ['client_order_id' => (string) Str::uuid(),
    'order_items' => [['id_product' => $susu['id'], 'quantity' => 1, 'price' => 6000]]]), $tokenB, 'HP-B');
Tenant::run(1, fn () => ok((float) StockBatch::find($batch1)->remaining_quantity === 10.0, 'batch kedaluwarsa tidak dijual'));

// =====================================================================
section('Panel superadmin');
foreach (['/superadmin', '/superadmin/toko', '/superadmin/toko/1', '/superadmin/toko/2', '/superadmin/pembayaran', '/superadmin/pembayaran?status=all', '/superadmin/paket', '/superadmin/log-jam', '/superadmin/settings'] as $uri) {
    [$st, $body] = web('GET', $uri, $superadmin);
    ok($st === 200, "GET $uri", $st === 200 ? null : $st . ' ' . substr(strip_tags($body), 0, 400));
}
[$st] = web('GET', '/dashboard', $superadmin);
ok($st === 403, 'superadmin tidak masuk dashboard toko', $st);
[$st, $body] = web('GET', '/', $superadmin);
ok($st === 200 && str_contains($body, 'Bulanan'), 'landing menampilkan paket');

// deteksi HP sama di toko lain
api('POST', '/login', ['email' => 'budi@x.id', 'password' => 'rahasia1', 'device_id' => 'HP-A']);
[$st, $body] = web('GET', '/superadmin/toko/1', $superadmin);
ok(str_contains($body, 'HP yang sama dipakai toko lain'), 'peringatan HP sama di toko lain');

[$st] = web('POST', '/superadmin/toko/2/lepas-kasir', $superadmin);
ok(Store::find(2)->active_device_id === null, 'superadmin lepas kasir');

echo "\n$pass lulus, $fail gagal\n";
exit($fail ? 1 : 0);
