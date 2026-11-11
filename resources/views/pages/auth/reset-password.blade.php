@extends('pages.auth._layout')

@section('title', 'Buat Password Baru')

@section('card')
    <header class="form-header">
        <span class="form-eyebrow">
            <i data-lucide="lock-keyhole"></i>
            Password baru
        </span>
        <h2>Buat password baru</h2>
        <p>Minimal 8 karakter. Setelah disimpan, masuk lagi dengan password ini.</p>
    </header>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <i data-lucide="circle-alert"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" id="authForm">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="form-group">
            <label for="email">Email</label>
            <div class="input-wrap">
                <i class="field-icon" data-lucide="mail"></i>
                <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}"
                    autocomplete="email" required readonly
                    @error('email') aria-invalid="true" @enderror>
            </div>
        </div>

        <div class="form-group">
            <label for="password">Password baru</label>
            <div class="input-wrap">
                <i class="field-icon" data-lucide="lock"></i>
                <input id="password" type="password" name="password" placeholder="Minimal 8 karakter"
                    autocomplete="new-password" required minlength="8" autofocus
                    @error('password') aria-invalid="true" @enderror>
                <button type="button" class="password-toggle" data-target="password" aria-label="Tampilkan password">
                    <i data-lucide="eye"></i>
                </button>
            </div>
            <div class="pw-meter" id="pwMeter" data-score="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
            <p class="pw-hint" id="pwHint">Gabungkan huruf besar, huruf kecil, angka, dan simbol agar lebih kuat.</p>
        </div>

        <div class="form-group">
            <label for="password_confirmation">Ulangi password baru</label>
            <div class="input-wrap">
                <i class="field-icon" data-lucide="lock-keyhole"></i>
                <input id="password_confirmation" type="password" name="password_confirmation"
                    placeholder="Ketik ulang password" autocomplete="new-password" required minlength="8">
                <button type="button" class="password-toggle" data-target="password_confirmation"
                    aria-label="Tampilkan password">
                    <i data-lucide="eye"></i>
                </button>
            </div>
            <p class="pw-hint" id="pwMatch"></p>
        </div>

        <button type="submit" class="submit-button" id="submitButton">
            <span class="btn-text">Simpan password baru</span>
            <i data-lucide="check"></i>
        </button>
    </form>

    <div class="security-note">
        <span><i data-lucide="shield-check"></i></span>
        <div>
            <strong>Tips</strong>
            <p>Jangan pakai password yang sama dengan email atau media sosial Anda.</p>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const pw = document.getElementById('password');
            const confirm = document.getElementById('password_confirmation');
            const meter = document.getElementById('pwMeter');
            const hint = document.getElementById('pwHint');
            const match = document.getElementById('pwMatch');
            const labels = ['Gabungkan huruf besar, huruf kecil, angka, dan simbol agar lebih kuat.', 'Lemah', 'Cukup', 'Kuat', 'Sangat kuat'];

            const score = (v) => {
                if (!v) return 0;
                let s = 0;
                if (v.length >= 8) s++;
                if (v.length >= 12) s++;
                if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
                if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
                return Math.max(1, Math.min(4, s));
            };

            const sync = () => {
                const s = score(pw.value);
                meter.dataset.score = s;
                hint.textContent = s ? 'Kekuatan: ' + labels[s] + (pw.value.length < 8 ? ' (minimal 8 karakter)' : '') : labels[0];
                match.textContent = confirm.value
                    ? (confirm.value === pw.value ? '✓ Password sama' : 'Password belum sama')
                    : '';
                match.style.color = confirm.value === pw.value ? '#0b8f6a' : '#dc2626';
            };

            pw.addEventListener('input', sync);
            confirm.addEventListener('input', sync);
        })();
    </script>
@endpush
