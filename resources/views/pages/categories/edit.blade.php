@extends('layouts.app')

@section('title', 'Edit Kategori')

@section('content')
    <div class="crud-page">

        <div class="p-header">
            <div>
                <a href="{{ route('categories.index') }}" class="p-back">
                    <i data-lucide="arrow-left"></i> Kembali ke kategori
                </a>
                <h1>Edit Kategori</h1>
                <p>{{ $category->name }}</p>
            </div>
        </div>

        <form action="{{ route('categories.update', $category->id) }}" method="POST" enctype="multipart/form-data"
            novalidate data-guard>
            @csrf
            @method('PUT')
            @include('pages.categories._form')
        </form>

    </div>
@endsection
