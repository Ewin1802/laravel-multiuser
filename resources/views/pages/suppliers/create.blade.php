@extends('layouts.app')

@section('title', 'Tambah Supplier')

@section('content')
    <div class="crud-page">

        <div class="p-header">
            <div>
                <a href="{{ route('suppliers.index') }}" class="p-back">
                    <i data-lucide="arrow-left"></i> Kembali ke supplier
                </a>
                <h1>Tambah Supplier</h1>
                <p>Daftarkan tempat membeli barang.</p>
            </div>
        </div>

        <form action="{{ route('suppliers.store') }}" method="POST" novalidate data-guard>
            @csrf
            @include('pages.suppliers._form')
        </form>

    </div>
@endsection
