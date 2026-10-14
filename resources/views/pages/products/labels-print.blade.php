@php
    $rp = fn ($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
    $perPage = $size === 'a4' ? 24 : 1;
    $pages = array_chunk($labels, $perPage);
@endphp
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label Barcode — {{ $storeName }}</title>
    <style>
        :root {
            --ink: #14171c;
            --muted: #667080;
            --accent: #ff6a1a;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #e9ebef;
            color: var(--ink);
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ---------- toolbar (tidak ikut tercetak) ---------- */
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: #14171c;
            color: #fff;
        }

        .toolbar strong { font-size: 15px; }
        .toolbar span { color: #c3c9d2; font-size: 13px; }
        .toolbar .spacer { flex: 1; }

        .toolbar button,
        .toolbar a {
            border: 0;
            border-radius: 10px;
            padding: 10px 16px;
            font: 600 14px/1 inherit;
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar .print { background: var(--accent); color: #fff; }
        .toolbar .back { background: rgba(255, 255, 255, .1); color: #fff; }

        .sheets {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            padding: 20px 12px 40px;
        }

        .page {
            background: #fff;
            box-shadow: 0 6px 24px rgba(0, 0, 0, .12);
        }

        /* ---------- label ---------- */
        .label {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #fff;
        }

        .label .store {
            color: var(--muted);
            font-size: 5.5pt;
            font-weight: 600;
            letter-spacing: .3pt;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label .name {
            font-size: 7.5pt;
            font-weight: 700;
            line-height: 1.15;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .label .bars { flex: 1; min-height: 0; margin-top: 1mm; }
        .label .bars svg { display: block; width: 100%; height: 100%; }

        .label .foot {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 2mm;
            margin-top: .6mm;
        }

        .label .code {
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 6.5pt;
            letter-spacing: .4pt;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label .price { font-size: 9pt; font-weight: 800; white-space: nowrap; }

        /* ---------- gulungan 50 × 30 mm ---------- */
        .size-roll .page { width: 50mm; height: 30mm; }
        .size-roll .label { width: 50mm; height: 30mm; padding: 1.6mm 2mm; }

        /* ---------- A4 3 × 8, label 64 × 34 mm ---------- */
        .size-a4 .page {
            width: 210mm;
            height: 297mm;
            padding: 12.5mm 7mm;
            display: grid;
            grid-template-columns: repeat(3, 64mm);
            grid-auto-rows: 33.9mm;
            column-gap: 2.5mm;
            align-content: start;
        }

        .size-a4 .label { padding: 2.5mm 3mm; outline: .2mm dashed #d5d9df; }
        .size-a4 .label .name { font-size: 8.5pt; }
        .size-a4 .label .store { font-size: 6pt; }
        .size-a4 .label .price { font-size: 10pt; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheets { display: block; padding: 0; }
            .page { box-shadow: none; break-after: page; page-break-after: always; }
            .page:last-child { break-after: auto; page-break-after: auto; }
            .size-a4 .label { outline: none; }
        }

        @page {
            @if ($size === 'a4')
                size: A4;
            @else
                size: 50mm 30mm;
            @endif
            margin: 0;
        }
    </style>
</head>

<body class="size-{{ $size }}">

    <div class="toolbar">
        <div>
            <strong>{{ count($labels) }} label siap dicetak</strong><br>
            <span>
                {{ $size === 'a4' ? count($pages) . ' lembar A4' : 'Ukuran 50 × 30 mm' }} ·
                Atur printer: margin <b>None/Tidak ada</b>, skala <b>100%</b>.
            </span>
        </div>
        <div class="spacer"></div>
        <a class="back" href="{{ route('products.labels') }}"
            onclick="if (window.opener || history.length === 1) { window.close(); }">Tutup</a>
        <button class="print" type="button" onclick="window.print()">Cetak Sekarang</button>
    </div>

    <div class="sheets">
        @foreach ($pages as $page)
            <div class="page">
                @foreach ($page as $label)
                    @php $product = $label['product']; @endphp
                    <div class="label">
                        <div class="store">{{ $storeName }}</div>
                        <div class="name">{{ $product->name }}</div>
                        <div class="bars">{!! $label['svg'] !!}</div>
                        <div class="foot">
                            <span class="code">{{ $product->barcode }}</span>
                            @if ($showPrice)
                                <span class="price">{{ $rp($product->price) }}</span>
                            @elseif ($product->code)
                                <span class="code">{{ $product->code }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

</body>

</html>
