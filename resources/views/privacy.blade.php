@php
    $s = $setting;
    $appName = 'My Cashier';
    $owner = $s->store_name ?: config('app.name', 'My Cashier');
    $email = $s->email ?: config('mail.from.address');
    $wa = $s->whatsapp ? preg_replace('/\D/', '', preg_replace('/^0/', '62', $s->whatsapp)) : null;
    $updated = '17 Oktober 2026';

    $toc = [
        'ringkasan' => 'Ringkasan',
        'data' => 'Data yang kami kumpulkan',
        'izin' => 'Izin di HP Android',
        'penggunaan' => 'Cara kami menggunakan data',
        'berbagi' => 'Berbagi data',
        'simpan' => 'Penyimpanan & keamanan',
        'retensi' => 'Lama penyimpanan',
        'hak' => 'Hak Anda',
        'hapus-akun' => 'Menghapus akun & data',
        'anak' => 'Anak-anak',
        'perubahan' => 'Perubahan kebijakan',
        'kontak' => 'Hubungi kami',
    ];
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kebijakan Privasi — {{ $appName }}</title>
    <meta name="description" content="Kebijakan privasi aplikasi kasir {{ $appName }}: data apa yang dikumpulkan, untuk apa, dan cara menghapus akun.">
    <link rel="icon" href="{{ $s->favicon ? $s->favicon_url : ($s->logo ? $s->logo_url : asset('images/logo-icon.png')) }}">
    <meta name="color-scheme" content="light dark">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f3f5f4;
            --surface: #ffffff;
            --ink: #14201b;
            --ink-2: #2c3a34;
            --muted: #5d6b65;
            --line: #dde4e0;
            --emerald: #0b8f6a;
            --emerald-deep: #076b50;
            --emerald-soft: #e2f4ed;
            --orange: #ff6a1a;
            --orange-soft: #fff0e6;
            --orange-ink: #b9480c;
            --hero-a: #063f30;
            --hero-b: #0b8f6a;
            --sans: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0d1311;
                --surface: #151d1a;
                --ink: #ecf2ef;
                --ink-2: #d0dad5;
                --muted: #93a39c;
                --line: #24312c;
                --emerald: #3fcf9f;
                --emerald-deep: #2bb487;
                --emerald-soft: rgba(63, 207, 159, .12);
                --orange-soft: rgba(255, 106, 26, .13);
                --orange-ink: #ff9a5e;
                --hero-a: #04261d;
                --hero-b: #075c45;
            }
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: var(--sans);
            font-size: 16px;
            line-height: 1.7;
            color: var(--ink-2);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
        }
        a { color: var(--emerald); text-underline-offset: 3px; }
        :focus-visible { outline: 3px solid var(--orange); outline-offset: 3px; border-radius: 6px; }
        .wrap { width: 100%; max-width: 1080px; margin: 0 auto; padding: 0 20px; }

        /* hero */
        .hero {
            position: relative;
            overflow: hidden;
            color: #fff;
            background: linear-gradient(135deg, var(--hero-a), var(--hero-b));
            padding: 22px 0 64px;
        }
        .hero::after {
            content: '';
            position: absolute;
            right: -120px; top: -140px;
            width: 420px; height: 420px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 106, 26, .35), transparent 65%);
            pointer-events: none;
        }
        .nav { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 44px; }
        .brand { display: inline-flex; align-items: center; gap: 10px; color: #fff; text-decoration: none; font-weight: 800; font-size: 18px; }
        .brand-mark {
            width: 36px; height: 36px; border-radius: 11px;
            display: grid; place-items: center;
            background: linear-gradient(135deg, #ff8a3d, var(--orange));
            box-shadow: 0 8px 20px rgba(255, 106, 26, .35);
        }
        .brand-mark svg { width: 20px; height: 20px; }
        .nav a.back {
            color: rgba(255, 255, 255, .85); text-decoration: none; font-weight: 600; font-size: 14px;
            padding: 8px 14px; border: 1px solid rgba(255, 255, 255, .25); border-radius: 999px;
        }
        .nav a.back:hover { background: rgba(255, 255, 255, .1); }
        .eyebrow {
            display: inline-block; font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
            color: #ffd2b5; margin-bottom: 10px;
        }
        h1 { margin: 0 0 12px; font-size: clamp(30px, 5vw, 44px); line-height: 1.15; color: #fff; letter-spacing: -.02em; }
        .hero p { margin: 0; max-width: 640px; color: rgba(255, 255, 255, .82); }
        .meta { margin-top: 18px; display: flex; flex-wrap: wrap; gap: 8px; }
        .chip {
            font-size: 13px; font-weight: 600; padding: 6px 12px; border-radius: 999px;
            background: rgba(255, 255, 255, .12); border: 1px solid rgba(255, 255, 255, .18); color: #fff;
        }

        /* layout */
        .layout { display: grid; grid-template-columns: 250px minmax(0, 1fr); gap: 28px; margin-top: -36px; padding-bottom: 60px; position: relative; z-index: 1; }
        .toc {
            position: sticky; top: 20px; align-self: start;
            background: var(--surface); border: 1px solid var(--line); border-radius: 18px; padding: 18px;
        }
        .toc b { display: block; font-size: 12px; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
        .toc ol { margin: 0; padding: 0; list-style: none; counter-reset: t; }
        .toc li { counter-increment: t; }
        .toc a {
            display: flex; gap: 10px; padding: 6px 8px; border-radius: 10px;
            color: var(--ink-2); text-decoration: none; font-size: 14px; line-height: 1.4;
        }
        .toc a::before { content: counter(t, decimal-leading-zero); color: var(--muted); font-weight: 700; font-size: 12px; padding-top: 2px; }
        .toc a:hover { background: var(--emerald-soft); color: var(--emerald-deep); }

        .doc { background: var(--surface); border: 1px solid var(--line); border-radius: 22px; padding: 34px clamp(20px, 4vw, 44px); min-width: 0; }
        section { scroll-margin-top: 20px; }
        section + section { margin-top: 34px; padding-top: 30px; border-top: 1px solid var(--line); }
        h2 { margin: 0 0 12px; font-size: 21px; line-height: 1.3; color: var(--ink); letter-spacing: -.01em; }
        h3 { margin: 18px 0 6px; font-size: 16px; color: var(--ink); }
        p { margin: 0 0 12px; }
        ul { margin: 0 0 12px; padding-left: 20px; }
        li { margin-bottom: 6px; }
        li::marker { color: var(--emerald); }
        strong { color: var(--ink); }

        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 6px; }
        .card { border: 1px solid var(--line); border-radius: 16px; padding: 16px; background: var(--bg); }
        .card .ic {
            width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center;
            background: var(--emerald-soft); color: var(--emerald); margin-bottom: 10px;
        }
        .card .ic svg { width: 18px; height: 18px; }
        .card strong { display: block; margin-bottom: 4px; font-size: 15px; }
        .card span { font-size: 14px; color: var(--muted); line-height: 1.55; display: block; }

        .table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 14px; margin: 6px 0 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 14.5px; min-width: 520px; }
        th, td { text-align: left; padding: 11px 14px; vertical-align: top; border-bottom: 1px solid var(--line); }
        th { background: var(--bg); color: var(--ink); font-weight: 700; font-size: 13px; }
        tr:last-child td { border-bottom: 0; }
        td:first-child { font-weight: 600; color: var(--ink); white-space: nowrap; }

        .note {
            border-left: 4px solid var(--orange); background: var(--orange-soft);
            border-radius: 0 14px 14px 0; padding: 14px 16px; margin: 12px 0; color: var(--ink-2);
        }
        .note strong { color: var(--orange-ink); }

        .steps { counter-reset: s; list-style: none; padding: 0; }
        .steps li { counter-increment: s; position: relative; padding-left: 42px; margin-bottom: 12px; }
        .steps li::before {
            content: counter(s); position: absolute; left: 0; top: 0;
            width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center;
            background: var(--emerald); color: #fff; font-weight: 700; font-size: 13px;
        }

        .contact { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
        .contact a {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-weight: 600;
            padding: 11px 16px; border-radius: 12px; border: 1px solid var(--line); color: var(--ink); background: var(--bg);
        }
        .contact a.primary { background: var(--emerald); border-color: transparent; color: #fff; }
        .contact svg { width: 18px; height: 18px; }

        footer { padding: 0 0 36px; color: var(--muted); font-size: 14px; text-align: center; }

        @media (max-width: 860px) {
            .layout { grid-template-columns: 1fr; }
            .toc { position: static; }
            .toc ol { columns: 2; column-gap: 8px; }
        }
        @media (max-width: 480px) {
            .toc ol { columns: 1; }
            .doc { border-radius: 18px; }
        }
        @media print {
            .hero { background: none; color: #000; padding: 0; }
            .hero h1, .hero p, .chip, .brand { color: #000; }
            .nav a.back, .toc { display: none; }
            .layout { display: block; margin: 0; }
            .doc { border: 0; padding: 0; }
        }
    </style>
</head>

<body>
    <header class="hero">
        <div class="wrap">
            <nav class="nav">
                <a class="brand" href="{{ url('/') }}">
                    <span class="brand-mark">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9"/><path d="M3 9h18v1a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0z"/><path d="M5 12v8h14v-8"/><path d="M10 20v-5h4v5"/></svg>
                    </span>
                    {{ $appName }}
                </a>
                <a class="back" href="{{ url('/') }}">← Beranda</a>
            </nav>
            <span class="eyebrow">Dokumen legal</span>
            <h1>Kebijakan Privasi</h1>
            <p>Halaman ini menjelaskan data apa yang dikumpulkan aplikasi {{ $appName }} (Android dan panel web), untuk apa data itu dipakai, dan bagaimana Anda bisa melihat atau menghapusnya.</p>
            <div class="meta">
                <span class="chip">Berlaku sejak {{ $updated }}</span>
                <span class="chip">Tanpa iklan</span>
                <span class="chip">Data tidak dijual</span>
            </div>
        </div>
    </header>

    <div class="wrap layout">
        <aside class="toc" aria-label="Daftar isi">
            <b>Daftar isi</b>
            <ol>
                @foreach ($toc as $id => $label)
                    <li><a href="#{{ $id }}">{{ $label }}</a></li>
                @endforeach
            </ol>
        </aside>

        <main class="doc">
            <section id="ringkasan">
                <h2>Ringkasan</h2>
                <p>{{ $appName }} adalah aplikasi kasir untuk pemilik toko dan cafe. Kebijakan ini berlaku untuk aplikasi Android {{ $appName }} dan panel web di <a href="{{ url('/') }}">{{ preg_replace('#^https?://#', '', url('/')) }}</a>, yang dikelola oleh {{ $owner }} (“kami”).</p>
                <div class="summary">
                    <div class="card">
                        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/></svg></div>
                        <strong>Data toko milik Anda</strong>
                        <span>Produk, transaksi, dan pelanggan adalah data toko Anda. Kami hanya menyimpan dan memprosesnya agar aplikasi berjalan.</span>
                    </div>
                    <div class="card">
                        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg></div>
                        <strong>Tidak dijual, tanpa iklan</strong>
                        <span>Kami tidak menjual data, tidak memasang iklan, dan tidak membagikan data untuk pemasaran pihak lain.</span>
                    </div>
                    <div class="card">
                        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 14h10l1-14"/></svg></div>
                        <strong>Bisa dihapus</strong>
                        <span>Anda dapat meminta akun dan seluruh data toko dihapus kapan saja. Lihat bagian <a href="#hapus-akun">Menghapus akun</a>.</span>
                    </div>
                </div>
            </section>

            <section id="data">
                <h2>Data yang kami kumpulkan</h2>
                <h3>1. Data yang Anda berikan</h3>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Jenis data</th><th>Contoh</th><th>Kegunaan</th></tr></thead>
                        <tbody>
                            <tr><td>Akun</td><td>Nama, email, nomor HP/WhatsApp, password (disimpan terenkripsi/hash)</td><td>Masuk ke aplikasi, pemulihan password, pemberitahuan akun</td></tr>
                            <tr><td>Profil toko</td><td>Nama toko, alamat, logo, keterangan struk</td><td>Ditampilkan di struk dan panel web</td></tr>
                            <tr><td>Data operasional toko</td><td>Produk, harga, stok, batch &amp; tanggal kedaluwarsa, supplier, stok masuk, pengeluaran</td><td>Fungsi inti kasir dan stok</td></tr>
                            <tr><td>Transaksi</td><td>Item terjual, total, metode bayar, diskon, waktu transaksi, pesanan meja</td><td>Struk, laporan penjualan, laba</td></tr>
                            <tr><td>Data pelanggan toko</td><td>Nama dan nomor HP pelanggan yang berutang (tempo/piutang), bila Anda mengisinya</td><td>Pencatatan piutang</td></tr>
                            <tr><td>Foto</td><td>Foto produk, logo, bukti pembayaran langganan</td><td>Ditampilkan di aplikasi / verifikasi pembayaran</td></tr>
                        </tbody>
                    </table>
                </div>
                <h3>2. Data yang terkumpul otomatis</h3>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Jenis data</th><th>Contoh</th><th>Kegunaan</th></tr></thead>
                        <tbody>
                            <tr><td>Info perangkat</td><td>ID perangkat aplikasi, merek &amp; model HP, versi Android, versi aplikasi</td><td>Membatasi satu HP kasir aktif per toko dan memverifikasi lisensi</td></tr>
                            <tr><td>Jam &amp; tanggal HP</td><td>Selisih jam HP dengan jam server saat sinkronisasi</td><td>Mencegah transaksi dimanipulasi dengan memundurkan tanggal</td></tr>
                            <tr><td>Log teknis</td><td>Alamat IP, waktu akses, pesan error server</td><td>Keamanan, mencegah penyalahgunaan, memperbaiki gangguan</td></tr>
                        </tbody>
                    </table>
                </div>
                <p>Kami <strong>tidak</strong> mengumpulkan lokasi Anda, kontak di HP, riwayat panggilan, SMS, atau file pribadi di luar foto yang Anda pilih sendiri.</p>
            </section>

            <section id="izin">
                <h2>Izin di HP Android</h2>
                <ul>
                    <li><strong>Kamera</strong> — untuk memindai barcode produk dan memotret produk. Gambar barcode hanya dibaca di HP, tidak dikirim ke server.</li>
                    <li><strong>Bluetooth / Perangkat sekitar</strong> — untuk mencari dan terhubung ke printer struk thermal.</li>
                    <li><strong>Lokasi</strong> (hanya Android versi lama) — Android 11 ke bawah mewajibkan izin ini untuk memindai perangkat Bluetooth. Aplikasi tidak membaca maupun menyimpan lokasi Anda.</li>
                    <li><strong>Foto / Galeri</strong> — untuk memilih foto produk atau logo toko.</li>
                    <li><strong>Internet</strong> — untuk sinkronisasi data dengan server.</li>
                </ul>
                <p>Anda bisa mencabut izin kapan saja lewat Pengaturan HP. Fitur yang terkait (misalnya cetak struk) tidak akan berfungsi sampai izin diberikan kembali.</p>
            </section>

            <section id="penggunaan">
                <h2>Cara kami menggunakan data</h2>
                <ul>
                    <li>Menjalankan fitur kasir, stok, laporan, dan mode meja.</li>
                    <li>Menyimpan transaksi saat offline lalu menyinkronkannya ke server saat online.</li>
                    <li>Mengelola akun, masa uji coba, langganan, dan lisensi perangkat.</li>
                    <li>Menjaga keamanan: mendeteksi manipulasi jam HP, akses mencurigakan, dan penyalahgunaan.</li>
                    <li>Menghubungi Anda tentang akun, persetujuan toko, masa aktif, atau perubahan layanan.</li>
                    <li>Memperbaiki gangguan dan meningkatkan aplikasi.</li>
                </ul>
                <p>Kami tidak menggunakan data toko Anda untuk iklan, tidak membuat profil pemasaran, dan tidak melatih pihak ketiga dengan data Anda.</p>
            </section>

            <section id="berbagi">
                <h2>Berbagi data</h2>
                <p>Kami tidak menjual atau menyewakan data. Data hanya dapat diakses pihak lain dalam keadaan berikut:</p>
                <ul>
                    <li><strong>Penyedia infrastruktur</strong> — perusahaan hosting server dan layanan email yang kami gunakan untuk menjalankan layanan. Mereka hanya memproses data atas nama kami.</li>
                    <li><strong>Google Play</strong> — saat Anda mengunduh aplikasi, Google memproses data sesuai kebijakan privasinya sendiri.</li>
                    <li><strong>Kewajiban hukum</strong> — bila diwajibkan oleh peraturan perundang-undangan atau perintah aparat yang berwenang.</li>
                </ul>
                <p>Di dalam satu toko, data dapat dilihat oleh pemilik toko dan kasir yang diberi akses oleh pemilik toko tersebut. Data satu toko tidak dapat dilihat oleh toko lain.</p>
            </section>

            <section id="simpan">
                <h2>Penyimpanan &amp; keamanan</h2>
                <ul>
                    <li>Semua komunikasi antara aplikasi, panel web, dan server dienkripsi dengan HTTPS.</li>
                    <li>Password disimpan dalam bentuk hash, bukan teks asli.</li>
                    <li>Lisensi perangkat ditandatangani secara digital sehingga tidak bisa dipalsukan.</li>
                    <li>Data tiap toko dipisahkan sehingga tidak tercampur dengan toko lain.</li>
                    <li>Server dicadangkan (backup) secara berkala untuk mencegah kehilangan data.</li>
                    <li>Transaksi offline disimpan sementara di HP sampai berhasil dikirim ke server.</li>
                </ul>
                <div class="note"><strong>Penting:</strong> jaga kerahasiaan password Anda dan kunci layar HP kasir. Kami tidak pernah meminta password Anda melalui telepon, chat, atau email.</div>
            </section>

            <section id="retensi">
                <h2>Lama penyimpanan</h2>
                <ul>
                    <li>Data akun dan toko disimpan selama akun Anda aktif.</li>
                    <li>Bila langganan berakhir, data tetap disimpan agar bisa digunakan kembali saat Anda memperpanjang, kecuali Anda meminta penghapusan.</li>
                    <li>Setelah permintaan hapus akun, data dihapus dari server aktif paling lambat <strong>30 hari</strong>, dan salinan di backup ikut terhapus saat backup tersebut diganti (paling lambat 30 hari berikutnya).</li>
                    <li>Data yang wajib disimpan karena hukum (misalnya catatan pembayaran langganan) dapat disimpan lebih lama sesuai ketentuan yang berlaku.</li>
                </ul>
            </section>

            <section id="hak">
                <h2>Hak Anda</h2>
                <p>Sesuai Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi, Anda berhak untuk:</p>
                <ul>
                    <li>Mengetahui dan mengakses data pribadi Anda yang kami simpan.</li>
                    <li>Memperbaiki data yang salah atau tidak lengkap (sebagian besar bisa langsung lewat aplikasi/panel web).</li>
                    <li>Meminta salinan data toko Anda.</li>
                    <li>Meminta penghapusan akun dan data.</li>
                    <li>Menarik persetujuan pemrosesan data, dengan konsekuensi layanan tidak dapat digunakan.</li>
                </ul>
                <p>Ajukan permintaan melalui kontak di bagian <a href="#kontak">Hubungi kami</a>. Kami akan menanggapi paling lambat 3 x 24 jam hari kerja.</p>
            </section>

            <section id="hapus-akun">
                <h2>Menghapus akun &amp; data</h2>
                <p>Anda dapat meminta penghapusan akun {{ $appName }} beserta seluruh data toko (produk, stok, transaksi, pelanggan, supplier, dan foto) dengan langkah berikut:</p>
                <ol class="steps">
                    <li>Kirim pesan ke {{ $email ? 'email' : 'kontak' }} kami{{ $wa ? ' atau WhatsApp' : '' }} dengan judul <strong>“Hapus Akun My Cashier”</strong>.</li>
                    <li>Sertakan <strong>email akun</strong> dan <strong>nama toko</strong>. Kirim dari email atau nomor yang terdaftar di akun agar kami bisa memastikan Anda pemiliknya.</li>
                    <li>Kami mengonfirmasi permintaan, lalu menonaktifkan akun dan menghapus data sesuai bagian <a href="#retensi">Lama penyimpanan</a>.</li>
                </ol>
                <div class="note"><strong>Perhatian:</strong> penghapusan tidak dapat dibatalkan. Bila Anda masih membutuhkan laporan, unduh atau cetak dulu dari panel web sebelum mengajukan penghapusan. Menghapus aplikasi dari HP saja <strong>tidak</strong> menghapus data di server.</div>
                @if ($email || $wa)
                    <div class="contact">
                        @if ($email)
                            <a class="primary" href="mailto:{{ $email }}?subject={{ rawurlencode('Hapus Akun My Cashier') }}&body={{ rawurlencode("Email akun:\nNama toko:\n\nSaya meminta akun dan seluruh data toko dihapus.") }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                                Minta hapus via email
                            </a>
                        @endif
                        @if ($wa)
                            <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Halo, saya ingin menghapus akun My Cashier. Email akun: ... Nama toko: ...') }}" target="_blank" rel="noopener">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 1-13.5 7.8L3 21l1.2-4.5A9 9 0 1 1 21 12z"/></svg>
                                Minta hapus via WhatsApp
                            </a>
                        @endif
                    </div>
                @endif
            </section>

            <section id="anak">
                <h2>Anak-anak</h2>
                <p>{{ $appName }} ditujukan untuk pemilik usaha dan karyawannya, bukan untuk anak-anak. Kami tidak dengan sengaja mengumpulkan data pribadi anak di bawah 18 tahun. Bila Anda mengetahui ada data anak yang tersimpan, hubungi kami agar segera dihapus.</p>
            </section>

            <section id="perubahan">
                <h2>Perubahan kebijakan</h2>
                <p>Kebijakan ini dapat kami perbarui bila ada fitur baru atau perubahan peraturan. Tanggal “Berlaku sejak” di atas akan ikut diperbarui. Untuk perubahan penting, kami akan memberi tahu melalui aplikasi atau email.</p>
            </section>

            <section id="kontak">
                <h2>Hubungi kami</h2>
                <p>Untuk pertanyaan tentang privasi, permintaan data, atau penghapusan akun:</p>
                <ul>
                    <li>Pengelola: <strong>{{ $owner }}</strong></li>
                    @if ($email)<li>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></li>@endif
                    @if ($s->whatsapp)<li>WhatsApp: <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener">{{ $s->whatsapp }}</a></li>@endif
                    @if ($s->phone && $s->phone !== $s->whatsapp)<li>Telepon: {{ $s->phone }}</li>@endif
                    @if ($s->address)<li>Alamat: {{ $s->address }}</li>@endif
                    <li>Situs: <a href="{{ url('/') }}">{{ url('/') }}</a></li>
                </ul>
            </section>
        </main>
    </div>

    <footer>
        <div class="wrap">{{ $s->copyright ?: '© ' . date('Y') . ' ' . $owner }}</div>
    </footer>
</body>

</html>
