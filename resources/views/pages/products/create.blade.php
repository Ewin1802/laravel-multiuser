@extends('layouts.app')

@section('title', 'Tambah Produk')

@section('content')
    <div class="crud-page">

        <div class="p-header">
            <div>
                <a href="{{ route('products.index') }}" class="p-back">
                    <i data-lucide="arrow-left"></i> Kembali ke produk
                </a>
                <h1>Tambah Produk</h1>
                <p>Tambahkan produk baru ke katalog toko.</p>
            </div>
            <div class="p-header-actions">
                <button type="button" class="s-guide-trigger" data-guide-open="multiUnitGuide">
                    <span class="s-guide-trigger-icon"><i data-lucide="sparkles"></i></span>
                    <span>Contoh: rokok slop · bungkus · batang</span>
                </button>
            </div>
        </div>

        <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data"
            id="productForm" novalidate data-guard>
            @csrf
            @include('pages.products._form')
        </form>

    </div>

    @include('pages.products._guide-multiunit')
@endsection
