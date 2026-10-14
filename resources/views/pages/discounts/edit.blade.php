@extends('layouts.app')

@section('title', 'Edit Diskon')

@section('content')
    <div class="crud-page">

        <div class="p-header">
            <div>
                <a href="{{ route('discounts.index') }}" class="p-back">
                    <i data-lucide="arrow-left"></i> Kembali ke diskon
                </a>
                <h1>Edit Diskon</h1>
                <p>{{ $discount->name }}</p>
            </div>
        </div>

        <form action="{{ route('discounts.update', $discount->id) }}" method="POST" novalidate data-guard>
            @csrf
            @method('PUT')
            @include('pages.discounts._form')
        </form>

    </div>
@endsection
