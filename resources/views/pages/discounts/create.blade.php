@extends('layouts.app')

@section('title', 'Tambah Diskon')

@section('content')
    <div class="crud-page">

        <div class="p-header">
            <div>
                <a href="{{ route('discounts.index') }}" class="p-back">
                    <i data-lucide="arrow-left"></i> Kembali ke diskon
                </a>
                <h1>Tambah Diskon</h1>
                <p>Buat potongan harga baru.</p>
            </div>
        </div>

        <form action="{{ route('discounts.store') }}" method="POST" novalidate data-guard>
            @csrf
            @include('pages.discounts._form')
        </form>

    </div>
@endsection
