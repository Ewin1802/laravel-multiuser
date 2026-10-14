@php
    $isEdit = isset($supplier);
    $sp = $supplier ?? null;
    $activeOn = (bool) old('is_active', $sp->is_active ?? true);
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
    <div class="p-form-main">
        <section class="p-card p-section">
            <div class="p-section-head">
                <span class="p-section-icon"><i data-lucide="truck"></i></span>
                <div>
                    <h3>Data Supplier</h3>
                    <p>Toko, distributor, atau sales tempat membeli barang.</p>
                </div>
            </div>

            <div class="p-grid">
                <div class="p-field">
                    <label for="name">Nama Supplier <span class="req">*</span></label>
                    <input type="text" id="name" name="name" maxlength="255" required autocomplete="off"
                        value="{{ old('name', $sp->name ?? '') }}" placeholder="Contoh: CV Sumber Part Manado"
                        class="@error('name') is-invalid @enderror">
                    @error('name')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field">
                    <label for="phone">Telepon / WhatsApp</label>
                    <input type="tel" id="phone" name="phone" maxlength="30" inputmode="tel"
                        value="{{ old('phone', $sp->phone ?? '') }}" placeholder="0812xxxxxxxx"
                        class="@error('phone') is-invalid @enderror">
                    @error('phone')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field p-span-2">
                    <label for="address">Alamat</label>
                    <input type="text" id="address" name="address" maxlength="255"
                        value="{{ old('address', $sp->address ?? '') }}" placeholder="Jl. ..., Kota"
                        class="@error('address') is-invalid @enderror">
                    @error('address')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field p-span-2">
                    <label for="notes">Catatan</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="1000"
                        placeholder="Contoh: tempo 14 hari, minimal order Rp1 juta, antar tiap Selasa."
                        class="@error('notes') is-invalid @enderror">{{ old('notes', $sp->notes ?? '') }}</textarea>
                    @error('notes')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>
            </div>
        </section>
    </div>

    <aside class="p-form-side">
        <section class="p-card p-section">
            <div class="p-toggle-row">
                <div>
                    <strong>Supplier aktif</strong>
                    <small>Supplier nonaktif tetap ada di riwayat & laporan, tapi turun ke bawah di pilihan Stok Masuk.</small>
                </div>
                <label class="p-switch">
                    <input type="checkbox" name="is_active" value="1" @checked($activeOn)>
                    <span class="p-switch-track"></span>
                    <span class="sr-only">Supplier aktif</span>
                </label>
            </div>

            <div class="p-form-actions">
                <a href="{{ route('suppliers.index') }}" class="p-btn p-btn-ghost">Batal</a>
                <button type="submit" class="p-btn p-btn-primary">
                    <i data-lucide="save"></i>
                    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Supplier' }}
                </button>
            </div>
        </section>
    </aside>
</div>

<div class="p-savebar">
    <a href="{{ route('suppliers.index') }}" class="p-btn p-btn-ghost">Batal</a>
    <button type="submit" class="p-btn p-btn-primary">
        <i data-lucide="save"></i>
        {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Supplier' }}
    </button>
</div>
