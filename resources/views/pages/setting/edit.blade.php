@extends('layouts.app')

@section('title', 'Pengaturan Platform')

@section('content')
    @php
        $s = $setting;
        $maintenanceOn = (bool) old('maintenance_mode', $s->maintenance_mode);
        $siteUrl = url('/');

        $sections = [
            'set-store' => ['Informasi Toko', 'store'],
            'set-brand' => ['Logo & Favicon', 'image'],
            'set-hero' => ['Hero Landing', 'layout-template'],
            'set-contact' => ['Kontak', 'phone'],
            'set-social' => ['Media Sosial', 'share-2'],
            'set-seo' => ['SEO', 'search'],
            'set-footer' => ['Footer', 'panel-bottom'],
            'set-server' => ['Server & Tagihan', 'server'],
            'set-rules' => ['Aturan Langganan', 'shield-check'],
        ];

        // section mana yang punya error → ditandai di navigasi
        $sectionFields = [
            'set-store' => ['store_name', 'store_tagline', 'store_description'],
            'set-brand' => ['logo', 'favicon'],
            'set-hero' => ['hero_title', 'hero_subtitle', 'hero_button'],
            'set-contact' => ['phone', 'whatsapp', 'email', 'address', 'google_maps'],
            'set-social' => ['facebook', 'instagram', 'youtube', 'tiktok'],
            'set-seo' => ['meta_title', 'meta_description', 'meta_keywords'],
            'set-footer' => ['copyright', 'maintenance_mode'],
            'set-server' => ['server_provider', 'server_due_date', 'server_billing_months', 'server_cost', 'server_remind_days'],
            'set-rules' => ['trial_days', 'grace_days', 'offline_limit_hours', 'offline_warn_hours', 'payment_instructions'],
        ];
        $hasError = fn($id) => $errors->hasAny($sectionFields[$id]);

        $socials = [
            'facebook' => ['Facebook', 'https://facebook.com/garasipart'],
            'instagram' => ['Instagram', 'https://instagram.com/garasipart'],
            'youtube' => ['YouTube', 'https://youtube.com/@garasipart'],
            'tiktok' => ['TikTok', 'https://tiktok.com/@garasipart'],
        ];

        // status jatuh tempo server (dari data yang TERSIMPAN, bukan isian yang sedang diketik)
        $server = \App\Support\ServerBilling::status($s);
        $serverTone = $server ? ['ok' => 'is-paid', 'soon' => 'is-due', 'urgent' => 'is-late', 'overdue' => 'is-late'][$server->level] : '';
        $serverDueValue = old('server_due_date', $s->server_due_date ? \Carbon\Carbon::parse($s->server_due_date)->format('Y-m-d') : '');
        $serverMonths = (int) old('server_billing_months', $s->server_billing_months ?? 1);
    @endphp

    <div class="crud-page">

        {{-- ===================== HEADER ===================== --}}
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Sistem</span>
                <h1>Pengaturan</h1>
                <p>Kelola informasi toko, tampilan landing page, kontak, dan SEO.</p>
            </div>

            <a href="{{ $siteUrl }}" target="_blank" rel="noopener" class="p-btn p-btn-ghost">
                <i data-lucide="external-link"></i>
                Lihat Website
            </a>
        </div>

        @if ($maintenanceOn && !$errors->any())
            <div class="p-notice is-warning" role="status">
                <i data-lucide="construction"></i>
                <div>
                    <strong>Website sedang mode maintenance.</strong>
                    <span>Pengunjung melihat halaman "sedang dalam perbaikan". Matikan di bagian Footer & Status.</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="p-notice is-danger" role="alert">
                <i data-lucide="circle-alert"></i>
                <div>
                    <strong>Pengaturan belum tersimpan. Periksa isian yang ditandai.</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            </div>
        @endif

        {{-- navigasi cepat (HP / tablet) --}}
        <nav class="p-jump" aria-label="Bagian pengaturan">
            <div class="p-chips">
                @foreach ($sections as $id => [$label, $icon])
                    <a href="#{{ $id }}" class="p-chip {{ $hasError($id) ? 'has-error' : '' }}"
                        data-spy="{{ $id }}">{{ $label }}</a>
                @endforeach
            </div>
        </nav>

        <form method="POST" action="{{ route('superadmin.settings.update') }}" enctype="multipart/form-data" novalidate
            data-guard>
            @csrf
            @method('PUT')

            <div class="p-form-layout">

                {{-- ===================== KOLOM UTAMA ===================== --}}
                <div class="p-form-main">

                    {{-- INFORMASI TOKO --}}
                    <section class="p-card p-section" id="set-store">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="store"></i></span>
                            <div>
                                <h3>Informasi Toko</h3>
                                <p>Nama dan deskripsi yang tampil di website & struk.</p>
                            </div>
                        </div>

                        <div class="p-grid">
                            <div class="p-field">
                                <label for="store_name">Nama Toko</label>
                                <input type="text" id="store_name" name="store_name" maxlength="255"
                                    value="{{ old('store_name', $s->store_name) }}" placeholder="Garasi Part"
                                    class="@error('store_name') is-invalid @enderror">
                                @error('store_name')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field">
                                <label for="store_tagline">Tagline</label>
                                <input type="text" id="store_tagline" name="store_tagline" maxlength="255"
                                    value="{{ old('store_tagline', $s->store_tagline) }}"
                                    placeholder="Contoh: Sparepart original, harga bengkel"
                                    class="@error('store_tagline') is-invalid @enderror">
                                @error('store_tagline')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field p-span-2">
                                <label for="store_description">Deskripsi Toko</label>
                                <textarea id="store_description" name="store_description" rows="3"
                                    placeholder="Deskripsi singkat toko. Dipakai juga sebagai deskripsi SEO bawaan."
                                    class="@error('store_description') is-invalid @enderror">{{ old('store_description', $s->store_description) }}</textarea>
                                @error('store_description')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </section>

                    {{-- LOGO & FAVICON --}}
                    <section class="p-card p-section" id="set-brand">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="image"></i></span>
                            <div>
                                <h3>Logo & Favicon</h3>
                                <p>Kosongkan jika tidak ingin mengganti.</p>
                            </div>
                        </div>

                        <div class="p-brand-grid">
                            <div class="p-field">
                                <span class="p-field-label">Logo</span>
                                <label class="p-upload is-wide @error('logo') is-invalid @enderror" data-upload
                                    data-max-mb="2">
                                    <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/webp"
                                        aria-label="Pilih logo">
                                    <img alt="Pratinjau logo" width="320" height="180"
                                        @if ($s->logo) src="{{ $s->logo_url }}" @else hidden @endif>
                                    <span class="p-upload-empty" @if ($s->logo) hidden @endif>
                                        <i data-lucide="image-plus"></i>
                                        <strong>Pilih atau seret logo</strong>
                                        <small>PNG/JPG, maks. 2 MB</small>
                                    </span>
                                </label>
                                <div class="p-upload-meta" hidden>
                                    <span data-upload-name></span>
                                    <button type="button" class="p-link" data-upload-reset>Batal</button>
                                </div>
                                @error('logo')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field">
                                <span class="p-field-label">Favicon</span>
                                <label class="p-upload is-square @error('favicon') is-invalid @enderror" data-upload
                                    data-max-mb="1">
                                    <input type="file" name="favicon" id="favicon"
                                        accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml"
                                        aria-label="Pilih favicon">
                                    <img alt="Pratinjau favicon" width="96" height="96"
                                        @if ($s->favicon) src="{{ $s->favicon_url }}" @else hidden @endif>
                                    <span class="p-upload-empty" @if ($s->favicon) hidden @endif>
                                        <i data-lucide="app-window"></i>
                                        <small>PNG/ICO<br>maks. 1 MB</small>
                                    </span>
                                </label>
                                <div class="p-upload-meta" hidden>
                                    <span data-upload-name></span>
                                    <button type="button" class="p-link" data-upload-reset>Batal</button>
                                </div>
                                @error('favicon')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </section>

                    {{-- HERO --}}
                    <section class="p-card p-section" id="set-hero">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="layout-template"></i></span>
                            <div>
                                <h3>Hero Landing Page</h3>
                                <p>Bagian paling atas halaman depan website.</p>
                            </div>
                        </div>

                        <div class="p-grid">
                            <div class="p-field p-span-2">
                                <label for="hero_title">Judul Hero</label>
                                <input type="text" id="hero_title" name="hero_title" maxlength="255"
                                    value="{{ old('hero_title', $s->hero_title) }}"
                                    placeholder="Contoh: Sparepart Original, Siap Pasang Hari Ini."
                                    class="@error('hero_title') is-invalid @enderror">
                                @error('hero_title')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field p-span-2">
                                <label for="hero_subtitle">Subjudul Hero</label>
                                <textarea id="hero_subtitle" name="hero_subtitle" rows="2"
                                    placeholder="Contoh: Oli, kampas rem, aki, dan ribuan part motor & mobil dengan garansi keaslian."
                                    class="@error('hero_subtitle') is-invalid @enderror">{{ old('hero_subtitle', $s->hero_subtitle) }}</textarea>
                                @error('hero_subtitle')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field">
                                <label for="hero_button">Teks Tombol</label>
                                <input type="text" id="hero_button" name="hero_button" maxlength="50"
                                    value="{{ old('hero_button', $s->hero_button) }}" placeholder="Contoh: Lihat Produk"
                                    class="@error('hero_button') is-invalid @enderror">
                                @error('hero_button')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </section>

                    {{-- KONTAK --}}
                    <section class="p-card p-section" id="set-contact">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="phone"></i></span>
                            <div>
                                <h3>Kontak</h3>
                                <p>Ditampilkan di website dan tombol chat WhatsApp.</p>
                            </div>
                        </div>

                        <div class="p-grid">
                            <div class="p-field">
                                <label for="phone">Nomor Telepon</label>
                                <input type="tel" id="phone" name="phone" inputmode="tel" autocomplete="tel"
                                    value="{{ old('phone', $s->phone) }}" placeholder="Contoh: 0431123456"
                                    class="@error('phone') is-invalid @enderror">
                                @error('phone')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field">
                                <label for="whatsapp">Nomor WhatsApp</label>
                                <input type="tel" id="whatsapp" name="whatsapp" inputmode="numeric"
                                    value="{{ old('whatsapp', $s->whatsapp) }}" placeholder="62812xxxxxxx"
                                    class="@error('whatsapp') is-invalid @enderror">
                                <small class="p-hint">
                                    Awali dengan 62, tanpa tanda +.
                                    <a href="#" class="p-link" id="waTest" target="_blank" rel="noopener" hidden>Tes chat</a>
                                </small>
                                @error('whatsapp')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field p-span-2">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" autocomplete="email"
                                    value="{{ old('email', $s->email) }}" placeholder="halo@garasipart.id"
                                    class="@error('email') is-invalid @enderror">
                                @error('email')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field p-span-2">
                                <label for="address">Alamat</label>
                                <textarea id="address" name="address" rows="2" autocomplete="street-address"
                                    placeholder="Jl. ..., Kota"
                                    class="@error('address') is-invalid @enderror">{{ old('address', $s->address) }}</textarea>
                                @error('address')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field p-span-2">
                                <label for="google_maps">Embed Google Maps</label>
                                <textarea id="google_maps" name="google_maps" rows="3" spellcheck="false"
                                    class="p-mono @error('google_maps') is-invalid @enderror"
                                    placeholder="Tempel kode <iframe> dari Google Maps → Bagikan → Sematkan peta">{{ old('google_maps', $s->google_maps) }}</textarea>
                                <small class="p-hint">
                                    <button type="button" class="p-link" id="mapPreviewBtn">Tampilkan pratinjau peta</button>
                                </small>
                                <div class="p-map-preview" id="mapPreview" hidden></div>
                                @error('google_maps')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </section>

                    {{-- MEDIA SOSIAL --}}
                    <section class="p-card p-section" id="set-social">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="share-2"></i></span>
                            <div>
                                <h3>Media Sosial</h3>
                                <p>Kosongkan yang tidak dipakai, ikonnya tidak akan tampil.</p>
                            </div>
                        </div>

                        <div class="p-grid">
                            @foreach ($socials as $field => [$label, $ph])
                                <div class="p-field">
                                    <label for="{{ $field }}">{{ $label }}</label>
                                    <input type="url" id="{{ $field }}" name="{{ $field }}" inputmode="url"
                                        value="{{ old($field, $s->{$field}) }}" placeholder="{{ $ph }}"
                                        class="@error($field) is-invalid @enderror">
                                    @error($field)
                                        <small class="p-error">{{ $message }}</small>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- SEO --}}
                    <section class="p-card p-section" id="set-seo">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="search"></i></span>
                            <div>
                                <h3>SEO</h3>
                                <p>Cara website tampil di hasil pencarian Google.</p>
                            </div>
                        </div>

                        {{-- pratinjau hasil Google (live) --}}
                        <div class="p-serp" aria-label="Pratinjau di Google">
                            <span class="p-serp-url">{{ preg_replace('#^https?://#', '', $siteUrl) }}</span>
                            <strong class="p-serp-title" id="serpTitle"></strong>
                            <p class="p-serp-desc" id="serpDesc"></p>
                        </div>

                        <div class="p-grid">
                            <div class="p-field p-span-2">
                                <label for="meta_title">
                                    Meta Title
                                    <span class="p-counter" data-counter-for="meta_title" data-limit="60"></span>
                                </label>
                                <input type="text" id="meta_title" name="meta_title" maxlength="255"
                                    value="{{ old('meta_title', $s->meta_title) }}"
                                    placeholder="Garasi Part — Sparepart Motor & Mobil Original"
                                    class="@error('meta_title') is-invalid @enderror">
                                @error('meta_title')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field p-span-2">
                                <label for="meta_description">
                                    Meta Description
                                    <span class="p-counter" data-counter-for="meta_description" data-limit="160"></span>
                                </label>
                                <textarea id="meta_description" name="meta_description" rows="2"
                                    placeholder="Ringkasan 1–2 kalimat yang membuat orang mengklik."
                                    class="@error('meta_description') is-invalid @enderror">{{ old('meta_description', $s->meta_description) }}</textarea>
                                @error('meta_description')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field p-span-2">
                                <label for="meta_keywords">Meta Keywords</label>
                                <input type="text" id="meta_keywords" name="meta_keywords"
                                    value="{{ old('meta_keywords', $s->meta_keywords) }}"
                                    placeholder="sparepart motor, oli mesin, kampas rem, aki mobil"
                                    class="@error('meta_keywords') is-invalid @enderror">
                                <small class="p-hint">Pisahkan dengan koma.</small>
                                @error('meta_keywords')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </section>

                    {{-- FOOTER --}}
                    <section class="p-card p-section" id="set-footer">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="panel-bottom"></i></span>
                            <div>
                                <h3>Footer</h3>
                                <p>Teks di bagian paling bawah website.</p>
                            </div>
                        </div>

                        <div class="p-field">
                            <label for="copyright">Teks Copyright</label>
                            <input type="text" id="copyright" name="copyright" maxlength="255"
                                value="{{ old('copyright', $s->copyright) }}"
                                placeholder="© {{ now()->year }} Garasi Part. Semua hak dilindungi."
                                class="@error('copyright') is-invalid @enderror">
                            @error('copyright')
                                <small class="p-error">{{ $message }}</small>
                            @enderror
                        </div>
                    </section>

                    {{-- SERVER & TAGIHAN --}}
                    <section class="p-card p-section" id="set-server">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="server"></i></span>
                            <div>
                                <h3>Server & Tagihan</h3>
                                <p>Catat jatuh tempo VPS/hosting. Peringatan muncul di navbar saat sudah dekat.</p>
                            </div>
                        </div>

                        @if ($server)
                            <dl class="p-meta">
                                <div class="{{ $serverTone }}">
                                    <dt>Jatuh tempo</dt>
                                    <dd>{{ $server->due->translatedFormat('d F Y') }}</dd>
                                </div>
                                <div class="{{ $serverTone }}">
                                    <dt>Status</dt>
                                    <dd>{{ $server->label }}</dd>
                                </div>
                            </dl>
                        @else
                            <div class="p-notice is-warning" role="status">
                                <i data-lucide="calendar-clock"></i>
                                <div>
                                    <strong>Tanggal jatuh tempo belum diisi.</strong>
                                    <span>Isi di bawah lalu simpan, supaya Anda diingatkan sebelum server mati.</span>
                                </div>
                            </div>
                        @endif

                        <div class="p-grid">
                            <div class="p-field">
                                <label for="server_provider">Penyedia Server</label>
                                <input type="text" id="server_provider" name="server_provider" maxlength="255"
                                    value="{{ old('server_provider', $s->server_provider) }}"
                                    placeholder="Contoh: Niagahoster VPS, DigitalOcean"
                                    class="@error('server_provider') is-invalid @enderror">
                                @error('server_provider')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field">
                                <label for="server_due_date">Tanggal Jatuh Tempo</label>
                                <input type="date" id="server_due_date" name="server_due_date"
                                    value="{{ $serverDueValue }}"
                                    class="@error('server_due_date') is-invalid @enderror">
                                <small class="p-hint">Tanggal tagihan berikutnya harus dibayar.</small>
                                @error('server_due_date')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field">
                                <label for="server_billing_months">Siklus Pembayaran</label>
                                <select id="server_billing_months" name="server_billing_months"
                                    class="@error('server_billing_months') is-invalid @enderror">
                                    @foreach (\App\Support\ServerBilling::CYCLES as $months => $cycle)
                                        <option value="{{ $months }}" @selected($serverMonths === $months)>{{ $cycle }}</option>
                                    @endforeach
                                </select>
                                @error('server_billing_months')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field">
                                <label for="server_cost_view">Biaya per Siklus (opsional)</label>
                                <div class="p-affix">
                                    <span class="is-prefix">Rp</span>
                                    <input type="text" id="server_cost_view" inputmode="numeric" autocomplete="off"
                                        placeholder="0" data-money="#server_cost"
                                        value="{{ old('server_cost', $s->server_cost) }}"
                                        class="has-prefix @error('server_cost') is-invalid @enderror">
                                </div>
                                <input type="hidden" name="server_cost" id="server_cost"
                                    value="{{ old('server_cost', $s->server_cost) }}">
                                @error('server_cost')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="p-field">
                                <label for="server_remind_days">Ingatkan Sejak</label>
                                <select id="server_remind_days" name="server_remind_days"
                                    class="@error('server_remind_days') is-invalid @enderror">
                                    @foreach ([7 => '7 hari sebelumnya', 14 => '14 hari sebelumnya', 30 => '30 hari sebelumnya', 60 => '60 hari sebelumnya'] as $days => $text)
                                        <option value="{{ $days }}" @selected((int) old('server_remind_days', $s->server_remind_days ?? 14) === $days)>{{ $text }}</option>
                                    @endforeach
                                </select>
                                <small class="p-hint">3 hari terakhir dan saat telat, peringatan berubah merah.</small>
                                @error('server_remind_days')
                                    <small class="p-error">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        @if ($server && Route::has('superadmin.settings.server-paid'))
                            <div class="p-pay-action">
                                {{-- form="serverPaidForm" → tombol ini mengirim form terpisah di bawah halaman --}}
                                <button type="submit" form="serverPaidForm" class="p-btn p-btn-ghost">
                                    <i data-lucide="check-check"></i>
                                    Sudah dibayar — majukan {{ $server->months === 12 ? '1 tahun' : $server->months . ' bulan' }}
                                </button>
                            </div>
                        @endif
                    </section>

                    {{-- aturan langganan --}}
                    <section class="p-card p-section" id="set-rules">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="shield-check"></i></span>
                            <div>
                                <h3>Aturan Langganan</h3>
                                <p>Berlaku untuk semua toko. Perubahan masuk ke HP kasir saat sinkron berikutnya.</p>
                            </div>
                        </div>
                        <div class="p-grid">
                            @foreach ([
                                'trial_days' => ['Lama trial (hari)', 3, 'Dihitung sejak toko disetujui. Satu kali per toko.'],
                                'grace_days' => ['Masa tenggang (hari)', 7, 'Hanya untuk toko yang pernah membayar. Trial habis langsung hanya-lihat.'],
                                'offline_limit_hours' => ['Batas offline (jam)', 48, 'Lewat dari ini, transaksi baru terkunci sampai HP sinkron.'],
                                'offline_warn_hours' => ['Peringatan offline mulai (jam)', 36, 'Kasir diingatkan untuk menyambungkan internet.'],
                            ] as $field => [$label, $default, $hint])
                                <div class="p-field">
                                    <label for="{{ $field }}">{{ $label }}</label>
                                    <input type="number" id="{{ $field }}" name="{{ $field }}" min="1" max="720" required
                                        value="{{ old($field, $s->$field ?? $default) }}" class="@error($field) is-invalid @enderror">
                                    <small class="p-hint">{{ $hint }}</small>
                                    @error($field)
                                        <small class="p-error">{{ $message }}</small>
                                    @enderror
                                </div>
                            @endforeach
                            <div class="p-field p-span-2">
                                <label for="payment_instructions">Instruksi pembayaran (rekening tujuan)</label>
                                <textarea id="payment_instructions" name="payment_instructions" rows="4"
                                    placeholder="Transfer ke BCA 1234567890 a.n. Nama Anda. Kirim bukti lewat aplikasi atau menu Langganan."
                                    class="@error('payment_instructions') is-invalid @enderror">{{ old('payment_instructions', $s->payment_instructions) }}</textarea>
                                <small class="p-hint">Tampil di halaman Langganan toko dan di aplikasi kasir.</small>
                            </div>
                        </div>
                    </section>
                </div>

                {{-- ===================== KOLOM SAMPING ===================== --}}
                <aside class="p-form-side">

                    {{-- navigasi bagian (laptop) --}}
                    <nav class="p-card p-side-nav" aria-label="Bagian pengaturan">
                        @foreach ($sections as $id => [$label, $icon])
                            <a href="#{{ $id }}" class="{{ $hasError($id) ? 'has-error' : '' }}" data-spy="{{ $id }}">
                                <i data-lucide="{{ $icon }}"></i>
                                {{ $label }}
                            </a>
                        @endforeach
                    </nav>

                    <section class="p-card p-section p-status-card {{ $maintenanceOn ? 'is-maint' : '' }}" id="set-status">
                        <div class="p-toggle-row">
                            <div>
                                <strong>Mode Maintenance</strong>
                                <small>Pengunjung melihat halaman "sedang dalam perbaikan". Admin tetap bisa masuk.</small>
                            </div>
                            <label class="p-switch">
                                <input type="checkbox" name="maintenance_mode" value="1" id="maintenance_mode"
                                    @checked($maintenanceOn)>
                                <span class="p-switch-track"></span>
                                <span class="sr-only">Aktifkan mode maintenance</span>
                            </label>
                        </div>

                        <p class="p-status-line" aria-live="polite">
                            <span class="p-status-dot"></span>
                            <span id="siteStatusText">{{ $maintenanceOn ? 'Website ditutup sementara' : 'Website online' }}</span>
                        </p>

                        <div class="p-form-actions">
                            <button type="submit" class="p-btn p-btn-primary">
                                <i data-lucide="save"></i>
                                Simpan Pengaturan
                            </button>
                        </div>
                    </section>

                </aside>
            </div>

            {{-- bar simpan HP / tablet --}}
            <div class="p-savebar is-single">
                <button type="submit" class="p-btn p-btn-primary">
                    <i data-lucide="save"></i>
                    Simpan Pengaturan
                </button>
            </div>
        </form>

        @if ($server && Route::has('superadmin.settings.server-paid'))
            <form method="POST" action="{{ route('superadmin.settings.server-paid') }}" id="serverPaidForm"
                data-confirm="Tandai server sudah dibayar? Jatuh tempo akan dimajukan ke periode berikutnya.">
                @csrf
            </form>
        @endif

    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const $ = (id) => document.getElementById(id);

            /* ---------- penghitung karakter SEO ---------- */
            document.querySelectorAll('[data-counter-for]').forEach((counter) => {
                const input = $(counter.dataset.counterFor);
                const limit = Number(counter.dataset.limit);
                const update = () => {
                    const n = input.value.trim().length;
                    counter.textContent = `${n}/${limit}`;
                    counter.classList.toggle('is-over', n > limit);
                };
                input.addEventListener('input', update);
                update();
            });

            /* ---------- pratinjau Google ---------- */
            const fallbackTitle = () => $('store_name').value.trim() || 'Garasi Part';
            const serp = () => {
                $('serpTitle').textContent = $('meta_title').value.trim() || fallbackTitle();
                $('serpDesc').textContent = $('meta_description').value.trim()
                    || $('store_description').value.trim()
                    || 'Belum ada deskripsi. Google akan mengambil potongan teks dari halaman.';
            };
            ['meta_title', 'meta_description', 'store_name', 'store_description']
                .forEach((id) => $(id).addEventListener('input', serp));
            serp();

            /* ---------- tes WhatsApp ---------- */
            const wa = $('whatsapp');
            const waTest = $('waTest');
            const waUpdate = () => {
                let n = wa.value.replace(/\D/g, '');
                if (n.startsWith('0')) n = '62' + n.slice(1);
                waTest.hidden = n.length < 9;
                waTest.href = 'https://wa.me/' + n;
            };
            wa.addEventListener('input', waUpdate);
            waUpdate();

            /* ---------- pratinjau Google Maps (hanya src embed resmi) ---------- */
            $('mapPreviewBtn').addEventListener('click', () => {
                const box = $('mapPreview');
                const match = $('google_maps').value.match(/src=["']([^"']+)["']/i);
                const src = match ? match[1].replace(/&amp;/g, '&') : '';
                box.hidden = false;

                if (!/^https:\/\/(www\.)?google\.[a-z.]+\/maps\/embed/i.test(src)) {
                    box.innerHTML = '<p>Kode embed tidak dikenali. Salin dari Google Maps → Bagikan → Sematkan peta.</p>';
                    return;
                }
                const frame = document.createElement('iframe');
                frame.src = src;
                frame.loading = 'lazy';
                frame.referrerPolicy = 'no-referrer-when-downgrade';
                frame.title = 'Pratinjau lokasi toko';
                box.replaceChildren(frame);
            });

            /* ---------- status maintenance ---------- */
            const maint = $('maintenance_mode');
            maint.addEventListener('change', () => {
                $('set-status').classList.toggle('is-maint', maint.checked);
                $('siteStatusText').textContent = maint.checked ? 'Website ditutup sementara' : 'Website online';
            });

            /* ---------- tandai bagian yang sedang dibaca ---------- */
            const links = document.querySelectorAll('[data-spy]');
            if ('IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach((e) => {
                        if (!e.isIntersecting) return;
                        links.forEach((a) => {
                            const on = a.dataset.spy === e.target.id;
                            a.classList.toggle('is-active', on);
                            if (on && a.closest('.p-jump')) {
                                a.parentElement.scrollTo({ left: a.offsetLeft - 16, behavior: 'smooth' });
                            }
                        });
                    });
                }, { rootMargin: '-35% 0px -60% 0px' });
                document.querySelectorAll('.p-form-main > section[id]').forEach((s) => io.observe(s));
            }

            // validasi server gagal → langsung ke bagian pertama yang bermasalah
            document.querySelector('.p-form-main .is-invalid')
                ?.closest('section')?.scrollIntoView({ block: 'start' });
        })();
    </script>
@endpush
