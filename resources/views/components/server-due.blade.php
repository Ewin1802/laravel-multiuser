{{-- =========================================================
     PERINGATAN JATUH TEMPO SERVER / VPS (navbar)
     Pasang di components/navbar.blade.php, di dalam .navbar-right:
         @include('components.server-due')
     Hanya tampil saat sudah waktunya diingatkan.
========================================================= --}}
@php
    $serverDue = \App\Support\ServerBilling::status($appSetting ?? \App\Models\Setting::first());
@endphp

@if ($serverDue && $serverDue->show)
    <a href="{{ Route::has('superadmin.settings.edit') ? route('superadmin.settings.edit') . '#set-server' : '#' }}"
        class="server-due is-{{ $serverDue->level }}"
        title="Pembayaran server{{ $serverDue->provider ? ' (' . $serverDue->provider . ')' : '' }} jatuh tempo {{ $serverDue->due->translatedFormat('d F Y') }}">
        <i data-lucide="server"></i>
        <span class="server-due-text">
            <small>Bayar server</small>
            <strong>{{ $serverDue->label }}</strong>
        </span>
    </a>
@endif
