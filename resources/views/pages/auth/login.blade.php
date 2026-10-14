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
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login | {{ $storeName }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>

<body>
    <main class="auth-page">

        {{-- ===================== SHOWCASE ===================== --}}
        <section class="auth-showcase">
            <div class="showcase-grid"></div>
            <div class="showcase-gear" aria-hidden="true"><i data-lucide="cog"></i></div>

            <div class="showcase-inner">
                <a href="{{ url('/') }}" class="brand">
                    <span class="brand-mark">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $storeName }}">
                        @else
                            <i data-lucide="settings"></i>
                        @endif
                    </span>
                    <span class="brand-copy">
                        <strong>{{ $storeName }}</strong>
                        <small>{{ $tagline }}</small>
                    </span>
                </a>

                <div class="showcase-content">
                    <span class="showcase-kicker">
                        <i data-lucide="wrench"></i>
                        Panel Pemilik Toko
                    </span>

                    <h1>Kelola toko,<br><em>lebih cepat.</em></h1>

                    <p>
                        Atur produk, stok, expire, transaksi kasir, dan laporan penjualan toko Anda.
                    </p>

                    <div class="showcase-features">
                        <div class="feature">
                            <span><i data-lucide="package"></i></span>
                            <div>
                                <strong>Stok Sparepart</strong>
                                <small>Pantau stok & harga part.</small>
                            </div>
                        </div>
                        <div class="feature">
                            <span><i data-lucide="receipt-text"></i></span>
                            <div>
                                <strong>Transaksi Kasir</strong>
                                <small>Penjualan cepat dan rapi.</small>
                            </div>
                        </div>
                        <div class="feature">
                            <span><i data-lucide="users"></i></span>
                            <div>
                                <strong>Piutang</strong>
                                <small>Penjualan tempo.</small>
                            </div>
                        </div>
                        <div class="feature">
                            <span><i data-lucide="chart-no-axes-combined"></i></span>
                            <div>
                                <strong>Laporan</strong>
                                <small>Produk terlaris & omzet.</small>
                            </div>
                        </div>
                    </div>
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

                <header class="form-header">
                    <span class="form-eyebrow">
                        <i data-lucide="lock-keyhole"></i>
                        Masuk ke akun
                    </span>
                    <h2>Selamat Datang</h2>
                    <p>Login untuk mengelola toko Anda.</p>
                </header>

                @if (session('success'))
                    <div class="alert alert-success" role="status">
                        <i data-lucide="circle-check"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert alert-success" role="status">
                        <i data-lucide="circle-check"></i>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <i data-lucide="circle-alert"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" id="authForm">
                    @csrf

                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-wrap">
                            <i class="field-icon" data-lucide="mail"></i>
                            <input id="email" type="email" name="email" value="{{ old('email') }}"
                                placeholder="nama@email.com" autocomplete="email" required autofocus
                                @error('email') aria-invalid="true" @enderror>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <i class="field-icon" data-lucide="lock"></i>
                            <input id="password" type="password" name="password" placeholder="Masukkan password"
                                autocomplete="current-password" required
                                @error('password') aria-invalid="true" @enderror>
                            <button type="button" class="password-toggle" data-target="password"
                                aria-label="Tampilkan password">
                                <i data-lucide="eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="auth-options">
                        <label class="remember">
                            <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                            <span>Ingat saya</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}">Lupa password?</a>
                        @endif
                    </div>

                    <button type="submit" class="submit-button" id="submitButton">
                        <span class="btn-text">Masuk</span>
                        <i data-lucide="arrow-right"></i>
                    </button>
                </form>

                @if (Route::has('register'))
                    <div class="switch-auth">
                        <span>Belum punya akun?</span>
                        <a href="{{ route('register') }}">Daftar sekarang</a>
                    </div>
                @endif

                <div class="security-note">
                    <span><i data-lucide="shield-check"></i></span>
                    <div>
                        <strong>Akses aman</strong>
                        <p>Jangan bagikan password Anda kepada siapa pun, termasuk yang mengaku sebagai admin.</p>
                    </div>
                </div>

                <footer class="form-footer">
                    <span>© {{ date('Y') }} {{ $storeName }}</span>
                    <i></i>
                    <a href="{{ url('/') }}">Kembali ke beranda</a>
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
            submitButton.classList.add('loading');
            submitButton.disabled = true;
        });
    </script>
</body>

</html>
