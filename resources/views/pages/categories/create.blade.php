@extends('layouts.app')

@section('title', 'Tambah Kategori')

@section('content')
    <div class="crud-page">

        <div class="p-header">
            <div>
                <a href="{{ route('categories.index') }}" class="p-back">
                    <i data-lucide="arrow-left"></i> Kembali ke kategori
                </a>
                <h1>Tambah Kategori</h1>
                <p>Buat kelompok baru untuk sparepart.</p>
            </div>
        </div>

        <form action="{{ route('categories.store') }}" method="POST" enctype="multipart/form-data" novalidate
            data-guard>
            @csrf
            @include('pages.categories._form')
        </form>

    </div>
@endsection
