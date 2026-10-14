@extends('layouts.app')

@section('title', 'Edit Stok Masuk')

@section('content')
    <div class="crud-page">

        <div class="p-header">
            <div>
                <a href="{{ route('stock-ins.index') }}" class="p-back">
                    <i data-lucide="arrow-left"></i> Kembali ke stok masuk
                </a>
                <h1>Edit Stok Masuk</h1>
                <p>{{ $stockIn->invoice_number ?: 'Nota #' . $stockIn->id }} · {{ $stockIn->received_at->translatedFormat('d M Y') }}</p>
            </div>
            <div class="p-header-actions">
                <button type="button" class="s-guide-trigger" data-guide-open="stockInGuide">
                    <span class="s-guide-trigger-icon"><i data-lucide="sparkles"></i></span>
                    <span>Panduan</span>
                </button>
            </div>
        </div>

        <form action="{{ route('stock-ins.update', $stockIn->id) }}" method="POST" novalidate data-guard>
            @csrf
            @method('PUT')
            @include('pages.stock-ins._form')
        </form>

    </div>
@endsection
