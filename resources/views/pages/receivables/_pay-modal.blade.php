{{-- ===================== MODAL TERIMA PEMBAYARAN PIUTANG =====================
     Dipakai di daftar & detail piutang. Tombol pemicu: [data-rc-pay]
     dengan data-url, data-customer, data-invoice, data-total, data-paid, data-remaining. --}}
<div class="p-modal p-paymodal" id="rcPayModal" data-today="{{ now()->toDateString() }}">
    <div class="p-modal-backdrop" data-rc-close></div>

    <div class="p-modal-dialog crud-page" role="dialog" aria-modal="true" aria-labelledby="rcPayTitle">
        <form method="POST" action="#" id="rcPayForm">
            @csrf

            <div class="p-modal-head">
                <span class="p-section-icon pay-head-icon"><i data-lucide="hand-coins"></i></span>
                <div>
                    <h2 id="rcPayTitle">Terima Pembayaran</h2>
                    <p id="rcPaySub">Pelunasan / cicilan piutang pelanggan.</p>
                </div>
                <button type="button" class="p-icon-btn p-modal-close" data-rc-close aria-label="Tutup">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="p-modal-body">
                <div class="pay-hero">
                    <div class="pay-hero-top">
                        <span class="pay-hero-label">Sisa tagihan</span>
                        <span class="pay-badge" id="rcPayInvoice">INV</span>
                    </div>
                    <strong class="pay-hero-amount" id="rcPayRemaining">Rp 0</strong>
                    <span class="pay-hero-to">
                        <i data-lucide="user-round"></i>
                        <span>dari <b id="rcPayCustomer">-</b></span>
                    </span>
                </div>

                <dl class="pay-facts">
                    <div><dt>Total nota</dt><dd id="rcPayTotal">-</dd></div>
                    <div><dt>Sudah dibayar</dt><dd id="rcPayPaid">-</dd></div>
                </dl>

                <div class="p-field pay-date">
                    <label for="rc_amount">Nominal diterima <span class="req">*</span></label>
                    <div class="pay-date-row">
                        <div class="p-money" style="flex:1">
                            <span>Rp</span>
                            <input type="text" id="rc_amount_display" inputmode="numeric" autocomplete="off" required>
                        </div>
                        <input type="hidden" name="amount" id="rc_amount">
                        <div class="pay-quick">
                            <button type="button" class="pay-quick-btn" data-rc-full>Lunasi</button>
                        </div>
                    </div>
                </div>

                <fieldset class="p-field p-fieldset">
                    <legend class="p-field-label">Metode</legend>
                    <div class="p-segmented">
                        <label><input type="radio" name="payment_method" value="Cash" checked><span><i data-lucide="banknote"></i> Cash</span></label>
                        <label><input type="radio" name="payment_method" value="Transfer"><span><i data-lucide="credit-card"></i> Transfer</span></label>
                    </div>
                </fieldset>

                <div class="p-grid">
                    <div class="p-field">
                        <label for="rc_paid_at">Tanggal bayar</label>
                        <input type="date" id="rc_paid_at" name="paid_at" max="{{ now()->toDateString() }}">
                    </div>
                    <div class="p-field">
                        <label for="rc_note">Catatan</label>
                        <input type="text" id="rc_note" name="note" maxlength="255" placeholder="Opsional">
                    </div>
                </div>

                <div class="pay-effect">
                    <span class="pay-effect-icon"><i data-lucide="arrow-down-left"></i></span>
                    <p id="rcPayEffect">Piutang berkurang sesuai nominal yang diterima.</p>
                </div>
            </div>

            <div class="p-modal-foot">
                <button type="button" class="p-btn p-btn-ghost" data-rc-close>Batal</button>
                <button type="submit" class="p-btn pay-submit" id="rcPaySubmit">
                    <i data-lucide="circle-check"></i>
                    <span>Simpan Pembayaran</span>
                </button>
            </div>
        </form>
    </div>
</div>

@once
    <style>
        .p-paymodal .p-modal-dialog { max-width: 500px; }
        .p-paymodal .p-modal-body { grid-auto-rows: max-content; }
        .p-paymodal .pay-head-icon { background: var(--pgs, #ecfdf3); color: var(--pg, #16a34a); }
        .p-paymodal .pay-hero { position: relative; display: grid; gap: 6px; padding: 18px 20px; overflow: hidden; border-radius: 18px; color: #fff;
            background: radial-gradient(120% 140% at 100% 0%, rgba(255,106,26,.38), transparent 55%), linear-gradient(135deg, #14171c, #0c0e11);
            box-shadow: 0 14px 30px rgba(12,14,17,.22); }
        .p-paymodal .pay-hero-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .p-paymodal .pay-hero-label { color: #aab1bb; font-size: 12px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; }
        .p-paymodal .pay-hero-amount { font-family: var(--phead, 'Barlow Condensed', sans-serif); font-size: 44px; font-weight: 800; line-height: 1; }
        .p-paymodal .pay-hero-to { display: inline-flex; align-items: center; gap: 8px; color: #d6dae0; font-size: 14px; }
        .p-paymodal .pay-hero-to svg { width: 16px; height: 16px; color: #ffae1f; }
        .p-paymodal .pay-hero-to b { color: #fff; }
        .p-paymodal .pay-badge { padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; color: #ffd58a; background: rgba(255,174,31,.14); border: 1px solid rgba(255,174,31,.35); }
        .p-paymodal .pay-facts { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); margin: 0; border: 1px solid var(--pl,#eef0f3); border-radius: 16px; overflow: hidden; }
        .p-paymodal .pay-facts > div { padding: 11px 14px; }
        .p-paymodal .pay-facts > div:first-child { border-right: 1px solid var(--pl,#eef0f3); }
        .p-paymodal .pay-facts dt { color: var(--pm,#667080); font-size: 12px; }
        .p-paymodal .pay-facts dd { margin: 2px 0 0; font-weight: 700; }
        .p-paymodal .pay-date { margin: 0; }
        .p-paymodal .pay-date-row { display: flex; gap: 8px; align-items: stretch; }
        .p-paymodal .pay-quick { display: flex; gap: 6px; }
        .p-paymodal .pay-quick-btn { padding: 0 14px; border: 1px solid var(--pb,#e5e7eb); border-radius: 12px; background: #fff; font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
        .p-paymodal .pay-quick-btn:hover, .p-paymodal .pay-quick-btn.is-active { border-color: var(--pa,#ff6a1a); background: var(--pas,#fff1e8); color: var(--pad,#e2550a); }
        .p-paymodal .pay-effect { display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; border-radius: 14px; background: var(--pgs,#ecfdf3); color: #14532d; font-size: 13px; line-height: 1.5; }
        .p-paymodal .pay-effect p { margin: 0; }
        .p-paymodal .pay-effect-icon { flex: none; display: grid; place-items: center; width: 28px; height: 28px; border-radius: 9px; background: #fff; color: var(--pg,#16a34a); }
        .p-paymodal .pay-submit { color: #fff; border: 0; background: linear-gradient(135deg, #22c55e, #15803d); box-shadow: 0 10px 22px rgba(21,128,61,.28); }
        .p-paymodal .pay-submit:disabled { opacity: .7; cursor: progress; }
        @media (max-width: 600px) { .p-paymodal .pay-hero-amount { font-size: 38px; } }
    </style>

    @push('scripts')
        <script>
            (() => {
                const modal = document.getElementById('rcPayModal');
                if (!modal) return;

                const $ = (id) => document.getElementById(id);
                const form = $('rcPayForm');
                const display = $('rc_amount_display');
                const hidden = $('rc_amount');
                const submit = $('rcPaySubmit');
                const rp = (n) => 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID');
                let remaining = 0;
                let paid = 0;
                let total = 0;
                let opener = null;

                const setAmount = (value) => {
                    const v = Math.max(0, Math.round(Number(value) || 0));
                    hidden.value = v || '';
                    display.value = v ? v.toLocaleString('id-ID') : '';
                    updateEffect();
                };

                const updateEffect = () => {
                    const v = Number(hidden.value) || 0;
                    const left = remaining - v;
                    modal.querySelector('[data-rc-full]').classList.toggle('is-active', v === remaining);
                    $('rcPayEffect').textContent = !v
                        ? 'Isi nominal yang diterima dari pelanggan.'
                        : left > 0
                            ? 'Setelah dicatat, sisa piutang menjadi ' + rp(left) + '.'
                            : left === 0
                                ? 'Nota akan berstatus LUNAS.'
                                : 'Nominal melebihi sisa tagihan ' + rp(-left) + ' (lebih bayar).';
                };

                display.addEventListener('input', () => {
                    const raw = display.value.replace(/\D/g, '');
                    setAmount(raw);
                });

                const open = (btn) => {
                    const d = btn.dataset;
                    opener = btn;
                    form.action = d.url;
                    remaining = Number(d.remaining) || 0;
                    paid = Number(d.paid) || 0;
                    total = Number(d.total) || 0;

                    $('rcPayRemaining').textContent = rp(remaining);
                    $('rcPayCustomer').textContent = d.customer;
                    $('rcPayInvoice').textContent = d.invoice;
                    $('rcPayTotal').textContent = rp(total);
                    $('rcPayPaid').textContent = rp(paid);
                    $('rc_paid_at').value = modal.dataset.today;
                    $('rc_note').value = '';
                    setAmount(remaining);

                    submit.disabled = false;
                    modal.classList.add('is-open');
                    window.refreshIcons?.();
                    setTimeout(() => display.focus(), 60);
                };

                const close = () => {
                    modal.classList.remove('is-open');
                    opener?.focus?.();
                };

                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-rc-pay]');
                    if (btn) { e.preventDefault(); open(btn); return; }
                    if (e.target.closest('[data-rc-close]')) close();
                    if (e.target.closest('[data-rc-full]')) setAmount(remaining);
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
                });

                form.addEventListener('submit', (e) => {
                    if (!(Number(hidden.value) > 0)) {
                        e.preventDefault();
                        display.focus();
                        return;
                    }
                    submit.disabled = true;
                    window.showLoading?.();
                });
            })();
        </script>
    @endpush
@endonce
