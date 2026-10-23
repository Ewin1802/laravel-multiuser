@php
    $setting = \App\Models\Setting::first();
    $appName = $setting?->store_name ?: config('app.name', 'My Cashier');
    $logoUrl = $setting?->logo ? $setting->logo_url : null;
    $trialDays = $setting?->trial_days ?: 3;
    $user = auth()->user();
    $store = $user?->store;
    $suspended = $store?->status === 'suspended';
    $rejected = $suspended && ! $store?->approved_at;

    // tombol WhatsApp admin (nomor dari Pengaturan superadmin)
    $wa = $setting?->whatsapp ? preg_replace('/\D/', '', preg_replace('/^0/', '62', $setting->whatsapp)) : null;
    $waText = $suspended
        ? "Halo admin {$appName}, saya pemilik toko {$store?->name} ({$user?->email}). Mohon info terkait status toko saya."
        : "Halo admin {$appName}, saya baru mendaftar toko {$store?->name} ({$user?->email}). Mohon dibantu persetujuannya.";

    $registered = $store?->created_at;
    $title = $suspended ? ($rejected ? 'Pendaftaran belum disetujui' : 'Toko sedang diblokir') : 'Pendaftaran diterima';
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0c0e11">
    <title>{{ $title }} | {{ $appName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #14171c;
            --ink-2: #2a2f37;
            --muted: #667080;
            --line: #e7e9ee;
            --card: #ffffff;
            --soft: #f6f7f9;
            --orange: #ff6a1a;
            --orange-deep: #d9500a;
            --gold: #ffae1f;
            --emerald: #0b8f6a;
            --red: #dc2626;
            --tone: var(--gold);
            --tone-soft: #fff4de;
            --tone-ink: #a2630c;
        }
        .is-blocked { --tone: #f87171; --tone-soft: #fdecec; --tone-ink: #b42318; }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: var(--ink);
            background: #0c0e11;
            -webkit-font-smoothing: antialiased;
        }

        /* ---------- latar ---------- */
        .bg {
            position: fixed;
            inset: 0;
            overflow: hidden;
            background: radial-gradient(1200px 600px at 85% -10%, rgba(255, 106, 26, .28), transparent 60%),
                radial-gradient(900px 500px at -10% 110%, rgba(11, 143, 106, .22), transparent 60%),
                linear-gradient(160deg, #1b1f26, #0c0e11 60%);
        }
        .bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255, 255, 255, .04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .04) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(ellipse at 50% 30%, #000 30%, transparent 75%);
        }

        .wrap {
            position: relative;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px 16px 40px;
        }
        .shell { width: 100%; max-width: 520px; }

        /* ---------- brand ---------- */
        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 22px;
            color: #fff;
            text-decoration: none;
            font-weight: 800;
            font-size: 17px;
        }
        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            overflow: hidden;
            background: linear-gradient(135deg, #ff8a3d, var(--orange));
            box-shadow: 0 8px 22px rgba(255, 106, 26, .35);
        }
        .brand-mark img { width: 100%; height: 100%; object-fit: cover; }
        .brand-mark svg { width: 21px; height: 21px; }

        /* ---------- kartu ---------- */
        .card {
            background: var(--card);
            border-radius: 28px;
            box-shadow: 0 40px 80px -30px rgba(0, 0, 0, .7), 0 0 0 1px rgba(255, 255, 255, .04);
            overflow: hidden;
        }
        .hero {
            position: relative;
            padding: 30px 28px 24px;
            text-align: center;
            background: linear-gradient(180deg, var(--tone-soft), #fff);
        }
        .status-ic {
            position: relative;
            width: 84px;
            height: 84px;
            margin: 0 auto 16px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #fff;
            box-shadow: 0 12px 28px -8px rgba(20, 23, 28, .25);
        }
        .status-ic svg { width: 38px; height: 38px; color: var(--tone-ink); }
        .status-ic::before,
        .status-ic::after {
            content: '';
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            border: 2px solid var(--tone);
            opacity: .55;
            animation: ring 2.4s ease-out infinite;
        }
        .status-ic::after { animation-delay: 1.2s; }
        .is-blocked .status-ic::before,
        .is-blocked .status-ic::after { animation: none; opacity: .35; }
        @keyframes ring {
            0% { transform: scale(.85); opacity: .7; }
            100% { transform: scale(1.35); opacity: 0; }
        }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid var(--line);
            color: var(--tone-ink);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .02em;
        }
        .chip i {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--tone);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--tone) 25%, transparent);
        }
        h1 {
            margin: 14px 0 8px;
            font-size: clamp(23px, 5vw, 27px);
            line-height: 1.2;
            letter-spacing: -.02em;
        }
        .lead {
            margin: 0 auto;
            max-width: 400px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.6;
        }
        .lead b { color: var(--ink); }

        .body { padding: 6px 24px 24px; }

        /* ---------- langkah ---------- */
        .steps {
            list-style: none;
            margin: 0 0 18px;
            padding: 16px 18px;
            border-radius: 18px;
            background: var(--soft);
        }
        .steps li {
            position: relative;
            display: grid;
            grid-template-columns: 28px 1fr;
            gap: 12px;
            padding-bottom: 16px;
        }
        .steps li:last-child { padding-bottom: 0; }
        .steps li:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 13px;
            top: 30px;
            bottom: 2px;
            width: 2px;
            background: var(--line);
        }
        .steps li.done:not(:last-child)::before { background: var(--emerald); }
        .dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 12px;
            font-weight: 800;
            background: #fff;
            border: 2px solid var(--line);
            color: var(--muted);
        }
        .dot svg { width: 14px; height: 14px; }
        .done .dot { background: var(--emerald); border-color: var(--emerald); color: #fff; }
        .now .dot {
            border-color: var(--tone);
            color: var(--tone-ink);
            box-shadow: 0 0 0 5px color-mix(in srgb, var(--tone) 22%, transparent);
        }
        .steps strong { display: block; font-size: 14px; line-height: 1.35; padding-top: 4px; }
        .steps small { display: block; color: var(--muted); font-size: 12.5px; margin-top: 2px; }
        .steps li:not(.done):not(.now) strong { color: var(--muted); font-weight: 600; }

        /* ---------- alasan blokir ---------- */
        .reason {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 16px;
            background: var(--tone-soft);
            border: 1px solid color-mix(in srgb, var(--tone) 35%, transparent);
            font-size: 14px;
            line-height: 1.5;
        }
        .reason span { display: block; font-size: 12px; font-weight: 700; color: var(--tone-ink); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px; }

        /* ---------- data ---------- */
        .info {
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
        }
        .info-row {
            display: grid;
            grid-template-columns: 36px minmax(0, 1fr);
            gap: 12px;
            align-items: center;
            padding: 12px 14px;
        }
        .info-row + .info-row { border-top: 1px solid var(--line); }
        .info-ic {
            width: 36px;
            height: 36px;
            border-radius: 11px;
            display: grid;
            place-items: center;
            background: #fff1e8;
            color: var(--orange-deep);
        }
        .info-ic svg { width: 18px; height: 18px; }
        .info-row small { display: block; color: var(--muted); font-size: 12px; }
        .info-row b { display: block; font-size: 14.5px; overflow-wrap: anywhere; }

        /* ---------- tombol ---------- */
        .actions { display: grid; gap: 10px; margin-top: 20px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            width: 100%;
            min-height: 52px;
            padding: 0 18px;
            border-radius: 16px;
            border: 1px solid transparent;
            font: 700 15px/1 'Plus Jakarta Sans', system-ui, sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .btn svg { width: 19px; height: 19px; flex: none; }
        .btn:hover { transform: translateY(-1px); }
        .btn:focus-visible { outline: 3px solid var(--orange); outline-offset: 3px; }
        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, #ff8a3d, var(--orange));
            box-shadow: 0 12px 24px -8px rgba(255, 106, 26, .55);
        }
        .btn-wa { color: #fff; background: #12a35a; box-shadow: 0 12px 24px -10px rgba(18, 163, 90, .6); }
        .btn-ghost { color: var(--ink-2); background: #fff; border-color: var(--line); }
        .btn-ghost:hover { background: var(--soft); }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .row-2 form { margin: 0; }

        .note {
            margin: 16px 2px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--muted);
            font-size: 12.5px;
            text-align: center;
        }
        .note svg { width: 15px; height: 15px; flex: none; }
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .foot { margin-top: 20px; text-align: center; color: rgba(255, 255, 255, .45); font-size: 12.5px; }
        .foot a { color: rgba(255, 255, 255, .7); }

        @media (max-width: 420px) {
            .hero { padding: 26px 18px 20px; }
            .body { padding: 4px 16px 18px; }
            .card { border-radius: 24px; }
            .row-2 { grid-template-columns: 1fr; }
        }
        @media (prefers-reduced-motion: reduce) {
            .status-ic::before, .status-ic::after, .spin { animation: none; }
        }
    </style>
</head>

<body class="{{ $suspended ? 'is-blocked' : '' }}">
    <div class="bg" aria-hidden="true"></div>

    <div class="wrap">
        <div class="shell">
            <a class="brand" href="{{ url('/') }}">
                <span class="brand-mark">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="">
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9"/><path d="M3 9h18v1a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0z"/><path d="M5 12v8h14v-8"/><path d="M10 20v-5h4v5"/></svg>
                    @endif
                </span>
                {{ $appName }}
            </a>

            <main class="card">
                <section class="hero">
                    <div class="status-ic" aria-hidden="true">
                        @if ($suspended)
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9.5 9.5l5 5"/><path d="M14.5 9.5l-5 5"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14"/><path d="M5 2h14"/><path d="M17 22v-4.17a2 2 0 0 0-.59-1.42L12 12l-4.41 4.41A2 2 0 0 0 7 17.83V22"/><path d="M7 2v4.17a2 2 0 0 0 .59 1.42L12 12l4.41-4.41A2 2 0 0 0 17 6.17V2"/></svg>
                        @endif
                    </div>

                    <span class="chip"><i></i>{{ $suspended ? ($rejected ? 'Tidak disetujui' : 'Diblokir') : 'Menunggu persetujuan' }}</span>
                    <h1>{{ $title }}</h1>

                    @if ($suspended)
                        <p class="lead">Akses toko <b>{{ $store?->name }}</b> sedang dibatasi oleh admin {{ $appName }}.
                            Hubungi admin bila menurut Anda ini keliru.</p>
                    @else
                        <p class="lead">Terima kasih, <b>{{ \Illuminate\Support\Str::of($user?->name)->explode(' ')->first() }}</b>!
                            Toko Anda sedang ditinjau admin. Masa uji coba <b>{{ $trialDays }} hari</b> baru dimulai
                            setelah disetujui, jadi tidak ada hari yang terbuang.</p>
                    @endif
                </section>

                <div class="body">
                    @if ($suspended)
                        @if ($store?->suspend_reason)
                            <div class="reason"><span>Alasan</span>{{ $store->suspend_reason }}</div>
                        @endif
                    @else
                        <ol class="steps" aria-label="Tahapan pendaftaran">
                            <li class="done">
                                <span class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span>
                                <div>
                                    <strong>Pendaftaran terkirim</strong>
                                    <small>{{ $registered ? $registered->translatedFormat('d M Y, H:i') . ' · ' . $registered->diffForHumans() : 'Baru saja' }}</small>
                                </div>
                            </li>
                            <li class="now">
                                <span class="dot">2</span>
                                <div>
                                    <strong>Ditinjau admin</strong>
                                    <small>Biasanya tidak lama. Halaman ini memeriksa otomatis.</small>
                                </div>
                            </li>
                            <li>
                                <span class="dot">3</span>
                                <div>
                                    <strong>Uji coba {{ $trialDays }} hari dimulai</strong>
                                    <small>Semua fitur terbuka, gratis.</small>
                                </div>
                            </li>
                            <li>
                                <span class="dot">4</span>
                                <div>
                                    <strong>Mulai berjualan</strong>
                                    <small>Login di aplikasi kasir Android & panel web ini.</small>
                                </div>
                            </li>
                        </ol>
                    @endif

                    <div class="info">
                        <div class="info-row">
                            <span class="info-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9"/><path d="M3 9h18v1a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0z"/><path d="M5 12v8h14v-8"/></svg></span>
                            <div><small>Toko</small><b>{{ $store?->name ?? '-' }}</b></div>
                        </div>
                        <div class="info-row">
                            <span class="info-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
                            <div><small>Pemilik</small><b>{{ $user?->name }}</b></div>
                        </div>
                        <div class="info-row">
                            <span class="info-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></span>
                            <div><small>Email</small><b>{{ $user?->email }}</b></div>
                        </div>
                        <div class="info-row">
                            <span class="info-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/></svg></span>
                            <div><small>No. HP</small><b>{{ $user?->phone_number }}</b></div>
                        </div>
                    </div>

                    <div class="actions">
                        @if ($wa)
                            <a class="btn btn-wa" href="https://wa.me/{{ $wa }}?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 1-13.5 7.8L3 21l1.2-4.5A9 9 0 1 1 21 12z"/></svg>
                                {{ $suspended ? 'Hubungi admin' : 'Minta dipercepat via WhatsApp' }}
                            </a>
                        @endif
                        <div class="row-2">
                            <a class="btn {{ $wa ? 'btn-ghost' : 'btn-primary' }}" href="{{ route('home') }}" id="checkNow">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                                Cek status
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>

                    @unless ($suspended)
                        <p class="note" id="autoNote">
                            <svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.2-8.56"/></svg>
                            <span>Diperiksa otomatis tiap 1 menit</span>
                        </p>
                    @endunless
                </div>
            </main>

            <p class="foot">
                © {{ date('Y') }} {{ $appName }}
                @if (Route::has('privacy')) · <a href="{{ route('privacy') }}">Kebijakan Privasi</a>@endif
            </p>
        </div>
    </div>

    @unless ($suspended)
        <script>
            // Cek status tiap 60 detik: /home mengarahkan ke dashboard bila sudah
            // disetujui, atau kembali ke halaman ini bila masih menunggu.
            (() => {
                const target = @json(route('home'));
                setInterval(() => {
                    if (document.visibilityState === 'visible') window.location.href = target;
                }, 60000);
            })();
        </script>
    @endunless
</body>

</html>
