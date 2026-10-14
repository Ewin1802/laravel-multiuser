@extends('layouts.app')

@section('title', 'Edit Supplier')

@section('content')
    <div class="crud-page">

        <div class="p-header">
            <div>
                <a href="{{ route('suppliers.index') }}" class="p-back">
                    <i data-lucide="arrow-left"></i> Kembali ke supplier
                </a>
                <h1>Edit Supplier</h1>
                <p>{{ $supplier->name }}</p>
            </div>
        </div>

        <form action="{{ route('suppliers.update', $supplier->id) }}" method="POST" novalidate data-guard>
            @csrf
            @method('PUT')
            @include('pages.suppliers._form')
        </form>

    </div>
@endsection
