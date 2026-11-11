@extends('pages.auth._layout')

@section('title', 'Lupa Password')

@section('card')
    @if (session('status'))
        {{-- tautan sudah dikirim --}}
        <div class="pw-sent">
            <div class="pw-sent-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
            </div>
            <header class="form-header" style="margin-bottom:0">
                <h2>Cek email Anda</h2>
            </header>
            <p>{{ session('status') }} Tautan berlaku 60 menit.</p>

            <div class="pw-tips">
                <strong>Email belum masuk?</strong>
                <ul>
                    <li>Tunggu 1–2 menit, lalu cek folder <b>Spam</b> atau <b>Promosi</b>.</li>
                    <li>Pastikan email yang diketik sama dengan saat mendaftar.</li>
                </ul>
            </div>

            {{-- Fortify tidak mengembalikan email ke halaman ini, jadi "kirim ulang" membuka form lagi --}}
            <a href="{{ route('password.request') }}" class="submit-button pw-resend" style="text-decoration:none">
                <span class="btn-text">Kirim ulang / ganti email</span>
                <i data-lucide="rotate-cw"></i>
            </a>
        </div>
    @else
        <header class="form-header">
            <span class="form-eyebrow">
                <i data-lucide="key-round"></i>
                Lupa password
            </span>
            <h2>Atur ulang password</h2>
            <p>Masukkan email akun Anda. Kami kirimkan tautan untuk membuat password baru.</p>
        </header>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <i data-lucide="circle-alert"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" id="authForm">
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

            <button type="submit" class="submit-button" id="submitButton">
                <span class="btn-text">Kirim tautan atur ulang</span>
                <i data-lucide="send"></i>
            </button>
        </form>

        <div class="switch-auth">
            <span>Ingat password?</span>
            <a href="{{ route('login') }}">Masuk</a>
        </div>
    @endif

    <div class="security-note">
        <span><i data-lucide="shield-check"></i></span>
        <div>
            <strong>Akses aman</strong>
            <p>Kami tidak pernah meminta password atau kode lewat telepon, chat, atau WhatsApp.</p>
        </div>
    </div>
@endsection
