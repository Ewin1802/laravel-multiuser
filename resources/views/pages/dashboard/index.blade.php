@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        // Helper format yang dipakai semua partial (partial mewarisi variabel ini)
        // spasi tak-terputus: "Rp" tidak pernah terpisah dari angkanya
        $rp = fn ($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $num = fn ($n) => number_format((float) ($n ?? 0), 0, ',', '.');
        // Stok desimal: 12.00 -> "12", 2.50 -> "2,5"
        $qty = fn ($n) => rtrim(rtrim(number_format((float) ($n ?? 0), 2, ',', '.'), '0'), ',');
    @endphp

    <div class="dashboard">

        @include('pages.dashboard.partials.header')

        {{-- pengingat hutang supplier: paling atas supaya pasti terlihat --}}
        @include('pages.dashboard.partials.payables')

        @include('pages.dashboard.partials.statistics')

        @include('pages.dashboard.partials.chart')

        <div class="dashboard-grid-2 dash-defer">
            @include('pages.dashboard.partials.products')
        </div>

    </div>
@endsection
