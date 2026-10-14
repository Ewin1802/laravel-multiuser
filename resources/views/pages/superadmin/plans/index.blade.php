@extends('layouts.app')

@section('title', 'Paket Langganan')

@section('content')
    <div class="crud-page">
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Superadmin</span>
                <h1>Paket Langganan</h1>
                <p>Harga yang tampil saat toko memperpanjang. Mengubah harga tidak mengubah pembayaran yang sudah tercatat.</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="p-notice is-danger" role="alert"><i data-lucide="circle-alert"></i><div><span>{{ $errors->first() }}</span></div></div>
        @endif

        @foreach ($plans->push(null) as $plan)
            <section class="p-card p-section">
                <form method="POST" action="{{ $plan ? route('superadmin.plans.update', $plan) : route('superadmin.plans.store') }}">
                    @csrf
                    @if ($plan) @method('PUT') @endif
                    <h3 style="margin:0 0 10px">{{ $plan ? $plan->name : 'Paket baru' }}</h3>
                    <div class="p-grid">
                        <div class="p-field">
                            <label>Kode</label>
                            <input type="text" name="code" required maxlength="30" value="{{ $plan->code ?? '' }}" placeholder="monthly">
                        </div>
                        <div class="p-field">
                            <label>Nama</label>
                            <input type="text" name="name" required maxlength="100" value="{{ $plan->name ?? '' }}" placeholder="Bulanan">
                        </div>
                        <div class="p-field">
                            <label>Durasi (bulan)</label>
                            <input type="number" name="duration_months" required min="1" max="36" value="{{ $plan->duration_months ?? 1 }}">
                        </div>
                        <div class="p-field">
                            <label>Harga (Rp)</label>
                            <input type="number" name="price" required min="0" value="{{ $plan->price ?? '' }}">
                        </div>
                    </div>
                    <div class="p-toggle-row">
                        <div><strong>Aktif</strong><small>Paket nonaktif tidak bisa dipilih toko.</small></div>
                        <label class="p-switch">
                            <input type="checkbox" name="is_active" value="1" @checked($plan->is_active ?? true)>
                            <span class="p-switch-track"></span>
                            <span class="sr-only">Aktif</span>
                        </label>
                    </div>
                    <button class="p-btn p-btn-primary" type="submit"><i data-lucide="save"></i> {{ $plan ? 'Simpan' : 'Tambah paket' }}</button>
                </form>
            </section>
        @endforeach
    </div>
@endsection
