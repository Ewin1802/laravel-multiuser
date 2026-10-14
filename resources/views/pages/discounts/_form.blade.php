@php
    $isEdit = isset($discount);
    $d = $discount ?? null;
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
                <span class="p-section-icon"><i data-lucide="badge-percent"></i></span>
                <div>
                    <h3>Informasi Diskon</h3>
                    <p>Nama, besar potongan, dan keterangannya.</p>
                </div>
            </div>

            <div class="p-grid">
                <div class="p-field">
                    <label for="name">Nama Diskon <span class="req">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $d->name ?? '') }}"
                        placeholder="Contoh: Bengkel Langganan" required maxlength="255" autocomplete="off"
                        class="@error('name') is-invalid @enderror">
                    @error('name')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field">
                    <label for="value">Nilai Diskon <span class="req">*</span></label>
                    <div class="p-affix">
                        <input type="number" id="value" name="value" min="0" max="100" step="0.01"
                            inputmode="decimal" value="{{ old('value', $d->value ?? '') }}" placeholder="0" required
                            class="has-suffix @error('value') is-invalid @enderror">
                        <span class="is-suffix">%</span>
                    </div>
                    @error('value')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field p-span-2">
                    <label for="description">Deskripsi <span class="req">*</span></label>
                    <textarea id="description" name="description" rows="5" required
                        class="@error('description') is-invalid @enderror"
                        placeholder="Contoh: Berlaku untuk bengkel mitra, semua kategori kecuali oli.">{{ old('description', $d->description ?? '') }}</textarea>
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
                <span class="p-section-icon"><i data-lucide="calculator"></i></span>
                <div>
                    <h3>Simulasi</h3>
                    <p>Contoh potongan untuk belanja.</p>
                </div>
            </div>

            <div class="p-field">
                <label for="simTotal">Total belanja</label>
                <div class="p-affix">
                    <span class="is-prefix">Rp</span>
                    <input type="text" id="simTotal" inputmode="numeric" value="500.000" class="has-prefix"
                        data-money="#simTotalRaw" aria-describedby="simResult">
                    <input type="hidden" id="simTotalRaw" value="500000">
                </div>
            </div>

            <dl class="p-sim" id="simResult" aria-live="polite">
                <div><dt>Potongan</dt><dd id="simCut">Rp 0</dd></div>
                <div class="is-total"><dt>Dibayar</dt><dd id="simPay">Rp 500.000</dd></div>
            </dl>

            <div class="p-form-actions">
                <a href="{{ route('discounts.index') }}" class="p-btn p-btn-ghost">Batal</a>
                <button type="submit" class="p-btn p-btn-primary">
                    <i data-lucide="save"></i>
                    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Diskon' }}
                </button>
            </div>
        </section>
    </aside>
</div>

{{-- Bar simpan yang selalu terlihat di HP / tablet --}}
<div class="p-savebar">
    <a href="{{ route('discounts.index') }}" class="p-btn p-btn-ghost">Batal</a>
    <button type="submit" class="p-btn p-btn-primary">
        <i data-lucide="save"></i>
        {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Diskon' }}
    </button>
</div>

@once
    @push('scripts')
        <script>
            (() => {
                const pct = document.getElementById('value');
                const total = document.getElementById('simTotal');
                const raw = document.getElementById('simTotalRaw');
                const rp = (v) => 'Rp ' + Math.round(v).toLocaleString('id-ID');

                const calc = () => {
                    const t = Number(raw.value) || 0;
                    const p = Math.min(100, Math.max(0, parseFloat(pct.value) || 0));
                    const cut = t * p / 100;
                    document.getElementById('simCut').textContent = '−' + rp(cut);
                    document.getElementById('simPay').textContent = rp(t - cut);
                };

                pct.addEventListener('input', calc);
                total.addEventListener('input', calc);
                calc();
            })();
        </script>
    @endpush
@endonce
