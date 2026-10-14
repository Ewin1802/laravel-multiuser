@php
    $isEdit = isset($category);
    $c = $category ?? null;
    $hasImage = $isEdit && $c->image;
@endphp

@if ($errors->any())
    <div class="p-notice is-danger" role="alert">
        <i data-lucide="circle-alert"></i>
        <div>
            <strong>Periksa kembali isian yang ditandai.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    </div>
@endif

<div class="p-form-layout">

    {{-- ===================== KOLOM UTAMA ===================== --}}
    <div class="p-form-main">
        <section class="p-card p-section">
            <div class="p-section-head">
                <span class="p-section-icon"><i data-lucide="layers-3"></i></span>
                <div>
                    <h3>Informasi Kategori</h3>
                    <p>Nama dan keterangan kelompok part.</p>
                </div>
            </div>

            <div class="p-grid">
                <div class="p-field p-span-2">
                    <label for="name">Nama Kategori <span class="req">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $c->name ?? '') }}"
                        placeholder="Contoh: Kampas Rem" required maxlength="255" autocomplete="off"
                        class="@error('name') is-invalid @enderror">
                    <small class="p-hint">Kata seperti oli, rem, aki, busi, filter, ban, lampu otomatis memberi ikon yang pas di website.</small>
                    @error('name')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field p-span-2">
                    <label for="description">Deskripsi <small class="p-hint">opsional</small></label>
                    <textarea id="description" name="description" rows="5"
                        class="@error('description') is-invalid @enderror"
                        placeholder="Contoh: Kampas rem depan & belakang, cakram maupun tromol, untuk motor dan mobil.">{{ old('description', $c->description ?? '') }}</textarea>
                    @error('description')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>
            </div>
        </section>
    </div>

    {{-- ===================== KOLOM SAMPING ===================== --}}
    <aside class="p-form-side">
        <section class="p-card p-section">
            <div class="p-section-head">
                <span class="p-section-icon"><i data-lucide="image"></i></span>
                <div>
                    <h3>Gambar Kategori</h3>
                    <p>{{ $isEdit ? 'Kosongkan jika tidak diganti.' : 'Opsional.' }}</p>
                </div>
            </div>

            <label class="p-upload @error('image') is-invalid @enderror" data-upload data-max-mb="2">
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">

                <img alt="Pratinjau gambar kategori"
                    @if ($hasImage) src="{{ asset($c->image) }}" @else hidden @endif>

                <span class="p-upload-empty" @if ($hasImage) hidden @endif>
                    <i data-lucide="image-plus"></i>
                    <strong>Pilih atau seret gambar</strong>
                    <small>JPG, PNG, atau WebP · maks. 2 MB</small>
                </span>
            </label>

            <div class="p-upload-meta" hidden>
                <span data-upload-name></span>
                <button type="button" class="p-link" data-upload-reset>Batal</button>
            </div>

            @error('image')
                <small class="p-error">{{ $message }}</small>
            @enderror

            <div class="p-form-actions">
                <a href="{{ route('categories.index') }}" class="p-btn p-btn-ghost">Batal</a>
                <button type="submit" class="p-btn p-btn-primary">
                    <i data-lucide="save"></i>
                    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kategori' }}
                </button>
            </div>
        </section>
    </aside>
</div>

{{-- Bar simpan yang selalu terlihat di HP / tablet --}}
<div class="p-savebar">
    <a href="{{ route('categories.index') }}" class="p-btn p-btn-ghost">Batal</a>
    <button type="submit" class="p-btn p-btn-primary">
        <i data-lucide="save"></i>
        {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kategori' }}
    </button>
</div>
