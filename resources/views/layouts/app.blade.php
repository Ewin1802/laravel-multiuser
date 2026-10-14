@php
    /*
    | Setting toko diambil SEKALI di sini, lalu dipakai ulang oleh
    | sidebar (variabel $appSetting) → hemat 1 query per halaman.
    */
    $appSetting = $appSetting ?? \App\Models\Setting::first();
    $appUser = auth()->user();
    $appStore = $appUser?->role === 'admin' ? $appUser->store : null;
    $appName = $appStore?->name ?? $appSetting?->store_name ?? config('app.name');
    $appFavicon = $appStore?->logo_url
        ?? ($appSetting?->logo ? $appSetting->logo_url : asset('icons/default-favicon.svg'));

    // Banner langganan untuk pemilik toko (trial / tenggang / habis / diblokir)
    $appSub = $appStore?->subscription();
    $appBanner = null;
    if ($appSub) {
        $st = $appSub->state();
        $left = $appSub->daysLeft();
        $appBanner = match (true) {
            $st === 'trial' => ['tone-blue', 'Masa uji coba', 'tersisa ' . $left . ' hari. Perpanjang supaya kasir tidak terkunci.'],
            $st === 'active' && $left !== null && $left <= 7 => ['tone-amber', 'Langganan', 'berakhir dalam ' . $left . ' hari.'],
            $st === 'grace' => ['tone-amber', 'Masa tenggang', 'kasir masih bisa transaksi ' . $left . ' hari lagi. Segera perpanjang.'],
            $st === 'expired' => ['tone-red', 'Masa aktif habis', 'data hanya bisa dilihat dan kasir tidak bisa transaksi baru.'],
            $st === 'suspended' => ['tone-red', 'Toko diblokir', 'hubungi admin aplikasi.'],
            default => null,
        };
    }

    $flashTypes = [
        'success' => ['success', 'Berhasil'],
        'error' => ['danger', 'Gagal'],
        'warning' => ['warning', 'Peringatan'],
        'info' => ['info', 'Informasi'],
    ];
    $flashes = collect($flashTypes)
        ->filter(fn($meta, $key) => session()->has($key))
        ->map(fn($meta, $key) => ['type' => $meta[0], 'title' => $meta[1], 'message' => session($key)])
        ->values();
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0c0e11">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Dashboard') | {{ $appName }}</title>

    <link rel="icon" href="{{ $appFavicon }}">

    {{-- ======================================================
         KONEKSI AWAL ke CDN (DNS + TLS dibuka lebih dulu)
    ======================================================= --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

    {{-- ======================================================
         FONT — sama dengan landing & halaman login
         display=swap: teks langsung tampil, tidak menunggu font
    ======================================================= --}}
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    {{-- ======================================================
         CSS UTAMA (render-blocking, memang dibutuhkan)
    ======================================================= --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}">
    {{-- sidebar & navbar dipakai di SEMUA halaman admin, jadi dimuat di sini.
         dashboard.css berisi gaya navbar atas + komponen kartu bersama. --}}
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    {{-- CSS bersama semua halaman kelola data (produk, kategori, user, dst.) --}}
    <link rel="stylesheet" href="{{ asset('css/pages.css') }}">
    <link rel="stylesheet" href="{{ asset('css/saas.css') }}">

    {{-- CSS khusus halaman --}}
    @stack('css')

    {{-- ======================================================
         FONT AWESOME — dimuat tanpa memblokir render
         (admin memakai Lucide; FA hanya cadangan untuk ikon lama)
    ======================================================= --}}
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    </noscript>
</head>

<body class="no-transition">

    {{-- Pulihkan status sidebar diringkas SEBELUM halaman tampil (tanpa kedip) --}}
    <script>
        try {
            if (localStorage.getItem('sidebar') === 'collapse' && innerWidth > 768) {
                document.body.classList.add('sidebar-collapse');
            }
        } catch (e) {}
    </script>

    <a href="#main-content" class="skip-link">Lewati ke konten</a>

    <div class="wrapper">

        {{-- SIDEBAR --}}
        @include('components.sidebar')

        {{-- MAIN --}}
        <main class="main">
            <div class="page">

                {{-- NAVBAR --}}
                @include('components.navbar')

                {{-- CONTENT --}}
                <div class="content" id="main-content" tabindex="-1">
                    @if ($appBanner && !request()->routeIs('billing.*'))
                        <div class="s-banner {{ $appBanner[0] }}" role="status">
                            <i data-lucide="shield-alert"></i>
                            <span><strong>{{ $appBanner[1] }}</strong> — {{ $appBanner[2] }}</span>
                            <a href="{{ route('billing.index') }}" class="p-btn p-btn-ghost">Perpanjang</a>
                        </div>
                    @endif
                    @yield('content')
                </div>

            </div>
        </main>

    </div>

    {{-- TOAST --}}
    <div class="toast-container" aria-live="polite" aria-atomic="true"></div>

    {{-- GLOBAL LOADING --}}
    <div class="loading" role="status" aria-live="polite">
        <div class="loading-content">
            <div class="spinner"></div>
            <div>Sedang memproses...</div>
        </div>
    </div>

    {{-- ======================================================
         LUCIDE — versi dikunci (bukan @latest) supaya tidak ada
         redirect tambahan & bisa di-cache lama oleh browser
    ======================================================= --}}
    <script src="https://cdn.jsdelivr.net/npm/lucide@0/dist/umd/lucide.min.js"></script>

    {{-- ======================================================
         APEXCHARTS sengaja TIDAK dimuat global lagi (±500 KB).
         - Dashboard memuatnya sendiri saat grafik terlihat.
         - Halaman lain yang butuh grafik cukup menambahkan:
             @push('scripts')
                 <script src="https://cdn.jsdelivr.net/npm/apexcharts@3/dist/apexcharts.min.js"></script>
                 ...kode grafik...
             @endpush
    ======================================================= --}}

    {{-- PROJECT JAVASCRIPT — semua fungsi admin ada di app.js --}}
    <script src="{{ asset('js/app.js') }}"></script>

    {{-- ======================================================
         INIT: ikon Lucide + notifikasi flash (digabung jadi satu)
    ======================================================= --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            window.lucide?.createIcons();

            const flashes = @json($flashes);

            if (typeof showToast === 'function') {
                flashes.forEach((f) => showToast(f.type, f.title, f.message));
            }
        });
    </script>

    {{-- SCRIPT KHUSUS HALAMAN --}}
    @stack('scripts')

</body>

</html>
