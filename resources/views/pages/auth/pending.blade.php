@php
    $setting = \App\Models\Setting::first();
    $appName = $setting?->store_name ?? config('app.name', 'Kasir');
    $user = auth()->user();
    $store = $user?->store;
    $suspended = $store?->status === 'suspended';
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $suspended ? 'Toko diblokir' : 'Menunggu persetujuan' }} | {{ $appName }}</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f6f7f9; font-family: Inter, system-ui, sans-serif; color: #1f2937; padding: 16px; }
        .box { max-width: 460px; width: 100%; background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
        h1 { font-size: 22px; margin: 0 0 8px; }
        p { color: #4b5563; line-height: 1.6; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 6px 14px; font-size: 14px; }
        dt { color: #6b7280; } dd { margin: 0; font-weight: 600; }
        button { margin-top: 16px; border: 0; background: #e2550a; color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 600; cursor: pointer; }
    </style>
</head>

<body>
    <main class="box">
        @if ($suspended)
            <h1>Toko diblokir</h1>
            <p>Akses toko <b>{{ $store->name }}</b> sedang dibatasi oleh admin aplikasi.
                {{ $store->suspend_reason ? 'Alasan: ' . $store->suspend_reason : '' }}</p>
        @else
            <h1>Pendaftaran diterima</h1>
            <p>Toko Anda sedang menunggu persetujuan admin. Masa uji coba
                {{ $setting->trial_days ?? 3 }} hari dimulai begitu disetujui. Halaman ini akan bisa dibuka setelah itu.</p>
        @endif
        <dl>
            <dt>Toko</dt><dd>{{ $store->name ?? '-' }}</dd>
            <dt>Pemilik</dt><dd>{{ $user->name }}</dd>
            <dt>Email</dt><dd>{{ $user->email }}</dd>
            <dt>No. HP</dt><dd>{{ $user->phone_number }}</dd>
        </dl>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </main>
</body>

</html>
