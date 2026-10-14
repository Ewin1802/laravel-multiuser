@extends('layouts.app')

@section('title', 'Kategori')

@section('content')
    @php $search = request('search'); @endphp

    <div class="crud-page">

        {{-- HEADER --}}
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Katalog</span>
                <h1>Kategori</h1>
                <p>Kelompokkan sparepart supaya mudah dicari pembeli.</p>
            </div>

            <a href="{{ route('categories.create') }}" class="p-btn p-btn-primary">
                <i data-lucide="plus"></i>
                Tambah Kategori
            </a>
        </div>

        {{-- Pesan sukses/gagal sudah tampil sebagai notifikasi (toast) dari layout --}}

        <div class="p-card">

            {{-- PENCARIAN --}}
            <form method="GET" action="{{ route('categories.index') }}" class="p-toolbar" role="search">
                <label class="p-search">
                    <i data-lucide="search"></i>
                    <input type="search" name="search" value="{{ $search }}" placeholder="Cari kategori..."
                        autocomplete="off" aria-label="Cari kategori">
                </label>

                <button type="submit" class="p-btn p-btn-dark">Cari</button>

                @if ($search)
                    <a href="{{ route('categories.index') }}" class="p-btn p-btn-ghost">Reset</a>
                @endif

                <span class="p-count">
                    <strong>{{ number_format($categories->total(), 0, ',', '.') }}</strong> kategori
                </span>
            </form>

            {{-- DAFTAR — tabel di layar lebar, kartu di layar sempit --}}
            <div class="p-table-wrap">
                <table class="p-table p-table-simple">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            @isset($categories->first()->products_count)
                                <th class="text-center">Produk</th>
                            @endisset
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td class="cell-main">
                                    <div class="p-item">
                                        <div class="p-thumb">
                                            @if ($category->image)
                                                <img src="{{ asset($category->image) }}" alt="{{ $category->name }}"
                                                    width="52" height="52" loading="lazy" decoding="async">
                                            @else
                                                <i data-lucide="layers-3"></i>
                                            @endif
                                        </div>
                                        <div class="p-item-text">
                                            <div class="p-name">{{ $category->name }}</div>
                                            <small
                                                class="p-desc">{{ $category->description ?: 'Belum ada deskripsi.' }}</small>
                                        </div>
                                    </div>
                                </td>

                                {{-- kolom jumlah produk muncul otomatis kalau controller memakai withCount('products') --}}
                                @isset($category->products_count)
                                    <td class="cell-stock text-center" data-label="Produk">
                                        <span class="p-category">{{ $category->products_count }} produk</span>
                                    </td>
                                @endisset

                                <td class="cell-actions">
                                    <div class="p-actions">
                                        <a href="{{ route('categories.edit', $category->id) }}" class="p-icon-btn"
                                            title="Edit" aria-label="Edit {{ $category->name }}">
                                            <i data-lucide="pencil"></i>
                                        </a>

                                        <form action="{{ route('categories.destroy', $category->id) }}" method="POST"
                                            data-confirm="Hapus kategori &quot;{{ $category->name }}&quot;? Produk di dalamnya bisa ikut terdampak.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-icon-btn is-danger" title="Hapus"
                                                aria-label="Hapus {{ $category->name }}">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="p-empty-row">
                                <td colspan="3">
                                    <div class="p-empty">
                                        <span class="p-empty-icon"><i data-lucide="folder-open"></i></span>
                                        @if ($search)
                                            <h4>Kategori tidak ditemukan</h4>
                                            <p>Tidak ada kategori dengan kata kunci "{{ $search }}".</p>
                                            <a href="{{ route('categories.index') }}" class="p-btn p-btn-ghost">Tampilkan
                                                semua</a>
                                        @else
                                            <h4>Belum ada kategori</h4>
                                            <p>Contoh: Oli & Pelumas, Kampas Rem, Aki, Busi.</p>
                                            <a href="{{ route('categories.create') }}" class="p-btn p-btn-primary">
                                                <i data-lucide="plus"></i> Tambah Kategori
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($categories->hasPages())
                <div class="p-footer">
                    <span class="p-page-info">
                        Menampilkan
                        <strong>{{ $categories->firstItem() }}</strong>–<strong>{{ $categories->lastItem() }}</strong>
                        dari <strong>{{ number_format($categories->total(), 0, ',', '.') }}</strong>
                    </span>
                    {{ $categories->withQueryString()->links('vendor.pagination.custom') }}
                </div>
            @endif
        </div>

    </div>
@endsection
