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
    <title>Daftar Akun | {{ $storeName }}</title>

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
                        <i data-lucide="user-plus"></i>
                        Daftarkan toko Anda
                    </span>

                    <h1>Kasir jalan.<br><em>Walau tanpa internet.</em></h1>

                    <p>
                        Daftar sekali, tunggu persetujuan, lalu coba gratis {{ $setting->trial_days ?? 3 }} hari.
                        Satu akun untuk satu toko.
                    </p>

                    <div class="showcase-features">
                        <div class="feature">
                            <span><i data-lucide="boxes"></i></span>
                            <div>
                                <strong>Multi-satuan</strong>
                                <small>Slop, bungkus, batang.</small>
                            </div>
                        </div>
                        <div class="feature">
                            <span><i data-lucide="scan-barcode"></i></span>
                            <div>
                                <strong>Scan Barcode</strong>
                                <small>Per satuan jual.</small>
                            </div>
                        </div>
                        <div class="feature">
                            <span><i data-lucide="truck"></i></span>
                            <div>
                                <strong>Offline</strong>
                                <small>Sinkron saat online.</small>
                            </div>
                        </div>
                        <div class="feature">
                            <span><i data-lucide="wrench"></i></span>
                            <div>
                                <strong>Expire (FEFO)</strong>
                                <small>Jual yang terdekat dulu.</small>
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
                        <i data-lucide="user-plus"></i>
                        Registrasi akun
                    </span>
                    <h2>Daftarkan Toko</h2>
                    <p>Setelah disetujui admin, masa uji coba langsung berjalan.</p>
                </header>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <i data-lucide="circle-alert"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('register') }}" id="authForm">
                    @csrf

                    <div class="form-group">
                        <label for="store_name">Nama Toko</label>
                        <div class="input-wrap">
                            <i class="field-icon" data-lucide="store"></i>
                            <input id="store_name" type="text" name="store_name" value="{{ old('store_name') }}"
                                placeholder="Contoh: Toko Sumber Rejeki" required maxlength="255"
                                @error('store_name') aria-invalid="true" @enderror>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone_number">No. HP / WhatsApp</label>
                        <div class="input-wrap">
                            <i class="field-icon" data-lucide="phone"></i>
                            <input id="phone_number" type="tel" name="phone_number" value="{{ old('phone_number') }}"
                                placeholder="08xxxxxxxxxx" required maxlength="20" autocomplete="tel"
                                @error('phone_number') aria-invalid="true" @enderror>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="name">Nama Pemilik</label>
                        <div class="input-wrap">
                            <i class="field-icon" data-lucide="user"></i>
                            <input id="name" type="text" name="name" value="{{ old('name') }}"
                                placeholder="Masukkan nama lengkap" autocomplete="name" required
                                @error('name') aria-invalid="true" @enderror>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-wrap">
                            <i class="field-icon" data-lucide="mail"></i>
                            <input id="email" type="email" name="email" value="{{ old('email') }}"
                                placeholder="nama@email.com" autocomplete="email" required
                                @error('email') aria-invalid="true" @enderror>
                        </div>
                    </div>

                    {{-- Dua kolom di tablet portrait, satu kolom di laptop & HP --}}
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrap">
                                <i class="field-icon" data-lucide="lock"></i>
                                <input id="password" type="password" name="password" placeholder="Buat password"
                                    autocomplete="new-password" required
                                    @error('password') aria-invalid="true" @enderror>
                                <button type="button" class="password-toggle" data-target="password"
                                    aria-label="Tampilkan password">
                                    <i data-lucide="eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation">Konfirmasi</label>
                            <div class="input-wrap">
                                <i class="field-icon" data-lucide="shield-check"></i>
                                <input id="password_confirmation" type="password" name="password_confirmation"
                                    placeholder="Ulangi password" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-target="password_confirmation"
                                    aria-label="Tampilkan password">
                                    <i data-lucide="eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="submit-button" id="submitButton">
                        <span class="btn-text">Buat Akun</span>
                        <i data-lucide="arrow-right"></i>
                    </button>
                </form>

                <div class="switch-auth">
                    <span>Sudah memiliki akun?</span>
                    <a href="{{ route('login') }}">Login</a>
                </div>

                <div class="security-note">
                    <span><i data-lucide="shield-check"></i></span>
                    <div>
                        <strong>Keamanan akun</strong>
                        <p>Gunakan email aktif dan password yang kuat (gabungan huruf dan angka).</p>
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

        const form = document.getElementById('authForm');
        const submitButton = document.getElementById('submitButton');

        form?.addEventListener('submit', () => {
            submitButton.classList.add('loading');
            submitButton.disabled = true;
        });
    </script>
</body>

</html>
