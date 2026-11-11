{{--
    Kerangka halaman auth sederhana (lupa password, atur ulang password).
    Tampilan sama dengan halaman login: panel kiri gelap + kartu form.
--}}
@php
    $setting = \App\Models\Setting::first();
    $storeName = $setting?->store_name ?? config('app.name', 'Kasir');
    $tagline = $setting?->store_tagline ?? 'Aplikasi kasir untuk toko Anda';
    $logoUrl = $setting?->logo ? $setting->logo_url : null;
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0c0e11">
    <meta name="robots" content="noindex">
    <title>@yield('title') | {{ $storeName }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <style>
        .pw-steps { list-style: none; margin: 22px 0 0; padding: 0; display: grid; gap: 14px; }
        .pw-steps li { display: grid; grid-template-columns: 34px 1fr; gap: 12px; align-items: start; }
        .pw-steps b {
            width: 34px; height: 34px; border-radius: 11px; display: grid; place-items: center;
            background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .12);
            color: #ffae1f; font-size: 14px;
        }
        .pw-steps strong { display: block; color: #fff; font-size: 14.5px; }
        .pw-steps small { display: block; color: rgba(255, 255, 255, .6); font-size: 13px; line-height: 1.5; margin-top: 2px; }
        .pw-hint { margin: 8px 2px 0; color: #6b7280; font-size: 12.5px; line-height: 1.5; }
        .pw-meter { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin-top: 8px; }
        .pw-meter i { height: 4px; border-radius: 4px; background: #e5e7eb; transition: background .2s; }
        .pw-meter[data-score="1"] i:nth-child(-n+1) { background: #ef4444; }
        .pw-meter[data-score="2"] i:nth-child(-n+2) { background: #f59e0b; }
        .pw-meter[data-score="3"] i:nth-child(-n+3) { background: #22c55e; }
        .pw-meter[data-score="4"] i { background: #0b8f6a; }
        .pw-sent {
            text-align: center; padding: 8px 0 4px;
        }
        .pw-sent-ic {
            width: 72px; height: 72px; margin: 0 auto 14px; border-radius: 22px; display: grid; place-items: center;
            background: linear-gradient(135deg, #ff8a3d, #ff6a1a); color: #fff;
            box-shadow: 0 14px 30px -10px rgba(255, 106, 26, .6);
        }
        .pw-sent-ic svg { width: 34px; height: 34px; }
        .pw-sent p { color: #4b5563; line-height: 1.6; margin: 6px 0 0; }
        .pw-tips { margin: 16px 0 0; padding: 14px 16px; border-radius: 14px; background: #f6f7f9; text-align: left; font-size: 13px; color: #4b5563; line-height: 1.55; }
        .pw-tips strong { color: #111827; }
        .pw-tips ul { margin: 6px 0 0; padding-left: 18px; }
        .pw-resend { margin-top: 14px; }
    </style>
</head>

<body>
    <main class="auth-page">

        {{-- ===================== SHOWCASE ===================== --}}
        <section class="auth-showcase">
            <div class="showcase-grid"></div>
            <div class="showcase-gear" aria-hidden="true"><i data-lucide="key-round"></i></div>

            <div class="showcase-inner">
                <a href="{{ url('/') }}" class="brand">
                    <span class="brand-mark">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $storeName }}">
                        @else
                            <i data-lucide="store"></i>
                        @endif
                    </span>
                    <span class="brand-copy">
                        <strong>{{ $storeName }}</strong>
                        <small>{{ $tagline }}</small>
                    </span>
                </a>

                <div class="showcase-content">
                    <span class="showcase-kicker">
                        <i data-lucide="shield-check"></i>
                        Pemulihan akun
                    </span>

                    <h1>Lupa password?<br><em>Tenang saja.</em></h1>

                    <ol class="pw-steps">
                        <li>
                            <b>1</b>
                            <div><strong>Masukkan email akun</strong><small>Email yang dipakai saat mendaftar toko.</small></div>
                        </li>
                        <li>
                            <b>2</b>
                            <div><strong>Buka tautan di email</strong><small>Berlaku 60 menit. Cek juga folder Spam.</small></div>
                        </li>
                        <li>
                            <b>3</b>
                            <div><strong>Buat password baru</strong><small>Lalu masuk lagi di web atau aplikasi kasir.</small></div>
                        </li>
                    </ol>
                </div>

                <div class="showcase-footer">
                    <span class="online"><i></i> System online</span>
                    <span>{{ $storeName }}</span>
                </div>
            </div>
        </section>

        {{-- ===================== FORM ===================== --}}
        <section class="auth-form-area">
            <div class="auth-card">
                @yield('card')

                <footer class="form-footer">
                    <span>© {{ date('Y') }} {{ $storeName }}</span>
                    <i></i>
                    <a href="{{ route('login') }}">Kembali ke login</a>
                    @if (Route::has('privacy'))
                        <i></i>
                        <a href="{{ route('privacy') }}">Privasi</a>
                    @endif
                </footer>
            </div>
        </section>
    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        // Tampilkan / sembunyikan password
        document.querySelectorAll('.password-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.target);
                if (!input) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                button.innerHTML = `<i data-lucide="${show ? 'eye-off' : 'eye'}"></i>`;
                lucide.createIcons();
            });
        });

        // Status loading saat submit
        const form = document.getElementById('authForm');
        const submitButton = document.getElementById('submitButton');
        form?.addEventListener('submit', () => {
            submitButton?.classList.add('loading');
            if (submitButton) submitButton.disabled = true;
        });
    </script>
    @stack('scripts')
</body>

</html>
