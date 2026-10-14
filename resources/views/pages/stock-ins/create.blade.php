@extends('layouts.app')

@section('title', 'Catat Stok Masuk')

@section('content')
    <div class="crud-page">

        <div class="p-header">
            <div>
                <a href="{{ route('stock-ins.index') }}" class="p-back">
                    <i data-lucide="arrow-left"></i> Kembali ke stok masuk
                </a>
                <h1>Catat Stok Masuk</h1>
                <p>Barang datang dari supplier. Stok produk bertambah otomatis.</p>
            </div>
            <div class="p-header-actions">
                <button type="button" class="s-guide-trigger" data-guide-open="stockInGuide">
                    <span class="s-guide-trigger-icon"><i data-lucide="sparkles"></i></span>
                    <span>Panduan</span>
                </button>
            </div>
        </div>

        <form action="{{ route('stock-ins.store') }}" method="POST" novalidate data-guard>
            @csrf
            @include('pages.stock-ins._form')
        </form>

    </div>
@endsection
