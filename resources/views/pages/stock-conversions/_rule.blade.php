{{-- ============================================================
     ATURAN BUKA KEMASAN: kalimat kunci + contoh BISA / TIDAK PERLU
     Dipakai di halaman Buka Kemasan & di panduan (tab "Pilih cara").
============================================================ --}}
<div class="s-cv-rule">
    <div class="s-cv-rule-key">
        <span class="s-cv-rule-icon"><i data-lucide="badge-info"></i></span>
        <p><b>Buka Kemasan hanya untuk barang yang setelah dibuka menjadi produk lain yang dijual ditakar</b>,
            seperti beras karung menjadi beras eceran per kg.</p>
    </div>

    <div class="s-cv-rule-cols">
        <div class="s-cv-rule-col is-yes">
            <span class="s-cv-rule-head"><i data-lucide="circle-check"></i> Bisa Buka Kemasan</span>
            <ul>
                <li><b>Beras karung 25 kg</b><span>→ Beras eceran per KG</span></li>
                <li><b>Gula / tepung karung</b><span>→ eceran per KG</span></li>
                <li><b>Minyak jeriken 18 L</b><span>→ Minyak curah per LITER</span></li>
                <li><b>Oli galon 20 L</b><span>→ Oli eceran per LITER</span></li>
                <li><b>Kabel / selang rol</b><span>→ eceran per METER</span></li>
            </ul>
            <small>Kemasan &amp; eceran = 2 produk. Catat di sini setiap kali kemasan dibuka.</small>
        </div>

        <div class="s-cv-rule-col is-no">
            <span class="s-cv-rule-head"><i data-lucide="circle-x"></i> Tidak perlu Buka Kemasan</span>
            <ul>
                <li><b>Rokok</b><span>SLOP · BUNGKUS · BATANG</span></li>
                <li><b>Air mineral</b><span>DUS · BOTOL</span></li>
                <li><b>Mi instan</b><span>DUS · BUNGKUS</span></li>
                <li><b>Kopi sachet</b><span>RENCENG · SACHET</span></li>
                <li><b>Susu kotak, sabun, odol</b><span>DUS / LUSIN · PCS</span></li>
            </ul>
            <small>1 produk dengan beberapa <b>satuan jual</b> (Produk → Edit). Stok terpotong otomatis,
                membuka slop / dus di rak tidak perlu dicatat.</small>
        </div>
    </div>
</div>

@once
    <style>
        .s-cv-rule {
            display: grid;
            gap: 14px;
            margin: 0 0 18px;
        }

        .s-cv-rule-key {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 16px 18px;
            border-radius: 18px;
            background: radial-gradient(120% 160% at 100% 0%, rgba(255, 106, 26, .22), transparent 60%),
                linear-gradient(135deg, #14171c, #22262d);
            color: #fff;
            box-shadow: 0 12px 28px rgba(12, 14, 17, .18);
        }

        .s-cv-rule-key p {
            margin: 0;
            font-size: 14.5px;
            line-height: 1.55;
            color: #d6dae0;
        }

        .s-cv-rule-key p b {
            color: #fff;
            background: linear-gradient(transparent 62%, rgba(255, 106, 26, .55) 62%);
        }

        .s-cv-rule-icon {
            flex: none;
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: linear-gradient(135deg, #ff6a1a, #ffae1f);
            box-shadow: 0 8px 18px rgba(255, 106, 26, .4);
        }

        .s-cv-rule-icon svg {
            width: 20px;
            height: 20px;
            color: #fff;
        }

        .s-cv-rule-cols {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .s-cv-rule-col {
            display: grid;
            align-content: start;
            gap: 10px;
            padding: 16px;
            border-radius: 16px;
            border: 1px solid;
        }

        .s-cv-rule-col.is-yes {
            background: linear-gradient(180deg, #ecfdf3, #fff 70%);
            border-color: #b7ebcb;
        }

        .s-cv-rule-col.is-no {
            background: linear-gradient(180deg, #f4f5f7, #fff 70%);
            border-color: #e3e6ea;
        }

        .s-cv-rule-head {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 800;
        }

        .s-cv-rule-head svg {
            width: 19px;
            height: 19px;
        }

        .is-yes .s-cv-rule-head { color: #15803d; }
        .is-no .s-cv-rule-head { color: #b91c1c; }

        .s-cv-rule-col ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 6px;
        }

        .s-cv-rule-col li {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 2px 10px;
            padding: 8px 10px;
            border-radius: 10px;
            background: #fff;
            border: 1px solid rgba(20, 23, 28, .06);
            font-size: 13px;
        }

        .s-cv-rule-col li span {
            color: #667080;
            font-size: 12.5px;
        }

        .is-no li span {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11.5px;
            letter-spacing: .02em;
        }

        .s-cv-rule-col small {
            color: #4b5563;
            font-size: 12px;
            line-height: 1.5;
        }

        @media (max-width: 720px) {
            .s-cv-rule-cols { grid-template-columns: 1fr; }
        }
    </style>
@endonce
