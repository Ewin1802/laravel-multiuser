@php
    $s = $setting;
    $appName = $s->store_name ?: config('app.name', 'Kasir');
    $trialDays = $s->trial_days ?: 3;
    $offlineHours = $s->offline_limit_hours ?: 48;
    $offlineText = $offlineHours % 24 === 0 ? $offlineHours / 24 . ' hari' : $offlineHours . ' jam';
    $wa = $s->whatsapp ? preg_replace('/\D/', '', preg_replace('/^0/', '62', $s->whatsapp)) : null;
    $rp = fn($n) => 'Rp' . number_format((float) $n, 0, ',', '.');
    $canRegister = Route::has('register');

    // hemat paket tahunan dibanding bayar bulanan 12x
    $monthly = $plans->firstWhere('duration_months', 1);
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $s->meta_title ?: $appName . ' — Aplikasi Kasir Offline' }}</title>
    <meta name="description"
        content="{{ $s->meta_description ?: 'Aplikasi kasir Android yang tetap jalan tanpa internet, dengan multi-satuan dan stok expire.' }}">
    @if ($s->meta_keywords)
        <meta name="keywords" content="{{ $s->meta_keywords }}">
    @endif
    <link rel="icon"
        href="{{ $s->favicon ? $s->favicon_url : ($s->logo ? $s->logo_url : asset('images/logo-icon.png')) }}">
    <meta name="color-scheme" content="light dark">
    <script>
        try {
            const t = localStorage.getItem('landing-theme');
            if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
        } catch (e) {}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --ink: #15181d;
            --ink-2: #2a2f37;
            --muted: #5b6472;
            --line: #dfe3e8;
            --fog: #eef1f4;
            --paper: #fbfaf7;
            --orange: #ff6a1a;
            --orange-deep: #d9500a;
            --amber: #ffae1f;
            --teal: #0f9d84;
            --radius: 20px;
            --max: 1160px;
            --sans: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --mono: 'JetBrains Mono', ui-monospace, Menlo, Consolas, monospace;

            /* permukaan (berubah di mode gelap) */
            --bg: #eef1f4;
            --surface: #ffffff;
            --hover: rgba(21, 24, 29, .05);
            --nav-bg: rgba(238, 241, 244, .82);
            --inverse: #15181d;
            --inverse-line: #15181d;
            --on-inverse: #ffffff;
            --on-inverse-muted: #b3bac5;
            --demo-bg: #fbfaf7;
            --demo-line: #d4d0c6;
            --demo-ink: #2d3036;
            --accent-text: #d9500a;
            --save-bg: #e3f6f1;
            --save-ink: #0b7a66;
            --shadow-lg: 0 40px 80px -30px rgba(21, 24, 29, .55);
            color-scheme: light;
        }

        /* ---------------- mode gelap ---------------- */
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --ink: #eef1f5;
                --ink-2: #d5dae1;
                --muted: #9aa3b0;
                --line: #262c35;
                --bg: #0f1215;
                --surface: #171b21;
                --hover: rgba(255, 255, 255, .06);
                --nav-bg: rgba(15, 18, 21, .8);
                --inverse: #1d222a;
                --inverse-line: #2f3640;
                --on-inverse: #ffffff;
                --on-inverse-muted: #a3acb8;
                --demo-bg: #11151a;
                --demo-line: #2f3640;
                --demo-ink: #d6dae0;
                --accent-text: #ff8a4c;
                --save-bg: rgba(15, 157, 132, .16);
                --save-ink: #4fd1b5;
                --shadow-lg: 0 40px 80px -30px rgba(0, 0, 0, .8);
                color-scheme: dark;
            }
        }

        :root[data-theme="dark"] {
            --ink: #eef1f5;
            --ink-2: #d5dae1;
            --muted: #9aa3b0;
            --line: #262c35;
            --bg: #0f1215;
            --surface: #171b21;
            --hover: rgba(255, 255, 255, .06);
            --nav-bg: rgba(15, 18, 21, .8);
            --inverse: #1d222a;
            --inverse-line: #2f3640;
            --on-inverse: #ffffff;
            --on-inverse-muted: #a3acb8;
            --demo-bg: #11151a;
            --demo-line: #2f3640;
            --demo-ink: #d6dae0;
            --accent-text: #ff8a4c;
            --save-bg: rgba(15, 157, 132, .16);
            --save-ink: #4fd1b5;
            --shadow-lg: 0 40px 80px -30px rgba(0, 0, 0, .8);
            color-scheme: dark;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: var(--sans);
            font-size: 16px;
            line-height: 1.6;
            color: var(--ink);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
            transition: background .25s ease, color .25s ease;
        }

        img {
            max-width: 100%;
            display: block;
        }

        a {
            color: inherit;
        }

        :focus-visible {
            outline: 3px solid var(--orange);
            outline-offset: 3px;
            border-radius: 8px;
        }

        .wrap {
            width: 100%;
            max-width: var(--max);
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ---------------- tombol ---------------- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 48px;
            padding: 0 22px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--surface);
            color: var(--ink);
            font: 600 15px/1 var(--sans);
            text-decoration: none;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
        }

        .btn svg {
            width: 18px;
            height: 18px;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            border-color: transparent;
            background: var(--orange);
            color: #fff;
            box-shadow: 0 10px 24px rgba(255, 106, 26, .28);
        }

        .btn-primary:hover {
            background: var(--orange-deep);
        }

        .btn-lg {
            min-height: 56px;
            padding: 0 28px;
            font-size: 16px;
            border-radius: 16px;
        }

        /* ---------------- navigasi ---------------- */
        .nav {
            position: sticky;
            top: 0;
            z-index: 20;
            background: var(--nav-bg);
            backdrop-filter: saturate(160%) blur(14px);
            -webkit-backdrop-filter: saturate(160%) blur(14px);
            border-bottom: 1px solid var(--line);
        }

        .nav .wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            height: 72px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-weight: 800;
            font-size: 18px;
            letter-spacing: -.01em;
        }

        .brand img,
        .brand .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            object-fit: cover;
        }

        .brand .brand-mark {
            display: grid;
            place-items: center;
            background: var(--inverse);
            color: var(--amber);
        }

        .brand .brand-mark svg {
            width: 20px;
            height: 20px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav-links a.link {
            padding: 10px 14px;
            border-radius: 10px;
            color: var(--muted);
            font-weight: 600;
            font-size: 14.5px;
            text-decoration: none;
        }

        .nav-links a.link:hover {
            color: var(--ink);
            background: var(--hover);
        }

        .theme-btn {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--surface);
            color: var(--ink);
            cursor: pointer;
        }

        .theme-btn svg {
            width: 18px;
            height: 18px;
        }

        .theme-btn .i-sun {
            display: none;
        }

        :root[data-theme="dark"] .theme-btn .i-sun {
            display: block;
        }

        :root[data-theme="dark"] .theme-btn .i-moon {
            display: none;
        }

        :root[data-theme-auto="dark"]:not([data-theme]) .theme-btn .i-sun {
            display: block;
        }

        :root[data-theme-auto="dark"]:not([data-theme]) .theme-btn .i-moon {
            display: none;
        }

        .nav .btn {
            min-height: 42px;
            padding: 0 18px;
            font-size: 14.5px;
        }

        /* ---------------- hero ---------------- */
        .hero {
            padding: 64px 0 88px;
        }

        .hero .wrap {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr);
            gap: 56px;
            align-items: center;
        }

        .tagline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px 6px 8px;
            border-radius: 999px;
            background: var(--surface);
            border: 1px solid var(--line);
            color: var(--ink-2);
            font-size: 13.5px;
            font-weight: 600;
        }

        .tagline .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--teal);
            box-shadow: 0 0 0 4px rgba(15, 157, 132, .16);
        }

        .hero h1 {
            margin: 22px 0 18px;
            font-size: clamp(38px, 5.4vw, 64px);
            line-height: 1.04;
            letter-spacing: -.035em;
            font-weight: 800;
            max-width: 14ch;
        }

        .hero h1 em {
            font-style: normal;
        }

        .lead {
            margin: 0;
            max-width: 46ch;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.65;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 32px;
        }

        .hero-facts {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 22px;
            margin: 28px 0 0;
            padding: 0;
            list-style: none;
            color: var(--muted);
            font-size: 14px;
            font-weight: 500;
        }

        .hero-facts li {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hero-facts svg {
            width: 17px;
            height: 17px;
            color: var(--accent-text);
        }

        /* ---------------- meja kasir (demo) ---------------- */
        .counter {
            position: relative;
            padding: 22px 22px 0;
            border-radius: 28px;
            background: linear-gradient(160deg, #1d2128, #12151a 60%);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--inverse-line);
            overflow: hidden;
        }

        .counter-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .net {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 6px 6px 6px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            color: #e8ebef;
            font-size: 13px;
            font-weight: 600;
        }

        .net-state {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .net-state i {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
        }

        .counter.is-offline .net-state i {
            background: var(--amber);
        }

        .switch {
            position: relative;
            width: 46px;
            height: 26px;
            border: 0;
            border-radius: 999px;
            background: #34d399;
            cursor: pointer;
            transition: background .2s ease;
        }

        .switch::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 23px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #fff;
            transition: left .2s ease;
        }

        .counter.is-offline .switch {
            background: #5b6472;
        }

        .counter.is-offline .switch::after {
            left: 3px;
        }

        .counter-hint {
            color: #8b94a3;
            font-size: 12.5px;
        }

        .printer {
            position: relative;
            margin: 22px auto 0;
            width: min(100%, 360px);
        }

        .printer-slot {
            position: relative;
            z-index: 2;
            height: 26px;
            border-radius: 14px 14px 6px 6px;
            background: linear-gradient(#2c323b, #22272e);
            box-shadow: inset 0 -6px 0 #0d0f12;
        }

        .receipt {
            position: relative;
            z-index: 1;
            margin: -8px 18px 0;
            padding: 24px 20px 30px;
            background: var(--paper);
            color: #23262b;
            font-family: var(--mono);
            font-size: 12.5px;
            line-height: 1.7;
            transform-origin: top;
            animation: print 1.6s cubic-bezier(.3, .7, .2, 1) .3s both;
            -webkit-mask: linear-gradient(#000 0 0) top / 100% calc(100% - 10px) no-repeat,
                conic-gradient(from -45deg at bottom, #0000, #000 1deg 89deg, #0000 90deg) bottom / 14px 10px repeat-x;
            mask: linear-gradient(#000 0 0) top / 100% calc(100% - 10px) no-repeat,
                conic-gradient(from -45deg at bottom, #0000, #000 1deg 89deg, #0000 90deg) bottom / 14px 10px repeat-x;
        }

        @keyframes print {
            from {
                clip-path: inset(0 0 100% 0);
                transform: translateY(-40px);
            }

            to {
                clip-path: inset(0 0 0 0);
                transform: translateY(0);
            }
        }

        .r-head {
            text-align: center;
            margin-bottom: 10px;
        }

        .r-head strong {
            display: block;
            font-size: 13.5px;
            letter-spacing: .08em;
        }

        .r-sep {
            border: 0;
            border-top: 1px dashed #b9bdc4;
            margin: 10px 0;
        }

        .r-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .r-row small {
            color: #6b717c;
        }

        .r-total {
            font-weight: 600;
            font-size: 14px;
        }

        .r-foot {
            margin-top: 12px;
            text-align: center;
            color: #6b717c;
            font-size: 11.5px;
        }

        .stamp-row {
            display: flex;
            justify-content: center;
            margin-top: 14px;
        }

        .stamp {
            display: inline-block;
            padding: 4px 12px;
            border: 2px solid var(--teal);
            border-radius: 8px;
            color: var(--teal);
            font: 700 11px/1.3 var(--sans);
            text-transform: uppercase;
            letter-spacing: .06em;
            transform: rotate(-8deg);
            background: rgba(251, 250, 247, .85);
        }

        .counter.is-offline .stamp {
            border-color: var(--orange-deep);
            color: var(--orange-deep);
        }

        .queue {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 0 -22px;
            padding: 14px 22px;
            background: rgba(255, 255, 255, .04);
            border-top: 1px solid rgba(255, 255, 255, .06);
            color: #aab1bb;
            font-size: 13px;
        }

        .queue svg {
            width: 16px;
            height: 16px;
        }

        .queue b {
            color: #fff;
        }

        /* ---------------- section umum ---------------- */
        section {
            padding: 88px 0;
        }

        section.alt {
            background: var(--surface);
        }

        .sec-head {
            max-width: 640px;
            margin-bottom: 44px;
        }

        .sec-head h2 {
            margin: 0 0 12px;
            font-size: clamp(28px, 3.6vw, 42px);
            line-height: 1.12;
            letter-spacing: -.03em;
            font-weight: 800;
        }

        .sec-head p {
            margin: 0;
            color: var(--muted);
            font-size: 17px;
        }

        /* masalah toko → cara aplikasi menangani */
        .fixes {
            display: grid;
            gap: 14px;
        }

        .fix {
            display: grid;
            grid-template-columns: 56px minmax(0, 1fr) minmax(0, 300px);
            gap: 22px;
            align-items: center;
            padding: 22px 24px;
            border-radius: var(--radius);
            background: var(--surface);
            border: 1px solid var(--line);
        }

        .fix-icon {
            display: grid;
            place-items: center;
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: var(--bg);
            color: var(--ink);
        }

        .fix-icon svg {
            width: 26px;
            height: 26px;
        }

        .fix h3 {
            margin: 0 0 4px;
            font-size: 18px;
            letter-spacing: -.01em;
        }

        .fix p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
        }

        .fix-demo {
            padding: 12px 14px;
            border-radius: 14px;
            background: var(--demo-bg);
            border: 1px dashed var(--demo-line);
            font-family: var(--mono);
            font-size: 12.5px;
            line-height: 1.6;
            color: var(--demo-ink);
        }

        .fix-demo b {
            color: var(--accent-text);
            font-weight: 600;
        }

        .fix-demo .ok {
            color: var(--teal);
            font-weight: 600;
        }

        .fix.is-lead {
            background: var(--inverse);
            border-color: var(--inverse-line);
            color: var(--on-inverse);
        }

        .fix.is-lead p {
            color: var(--on-inverse-muted);
        }

        .fix.is-lead .fix-demo {
            background: #1f242b;
            border-color: #3a414c;
            color: #d6dae0;
        }

        .fix.is-lead .fix-demo .ok {
            color: #34d399;
        }

        .fix.is-lead .fix-icon {
            background: var(--orange);
            color: #fff;
        }

        /* langkah mulai */
        .steps {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0;
            counter-reset: step;
            border-top: 2px solid var(--ink);
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .step {
            padding: 26px 26px 0 0;
        }

        .step+.step {
            padding-left: 26px;
            border-left: 1px solid var(--line);
        }

        .step-no {
            display: block;
            margin-bottom: 14px;
            color: var(--accent-text);
            font-weight: 800;
            font-size: 15px;
        }

        .step h3 {
            margin: 0 0 6px;
            font-size: 20px;
            letter-spacing: -.01em;
        }

        .step p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
        }

        /* harga */
        .plans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            max-width: 760px;
        }

        .plan {
            position: relative;
            padding: 28px;
            border-radius: 24px;
            background: var(--surface);
            border: 1px solid var(--line);
        }

        .plan h3 {
            margin: 0;
            font-size: 16px;
            color: var(--muted);
            font-weight: 600;
        }

        .plan .price {
            margin: 10px 0 2px;
            font-size: 40px;
            font-weight: 800;
            letter-spacing: -.03em;
            line-height: 1.1;
        }

        .plan .per {
            color: var(--muted);
            font-size: 14.5px;
        }

        .plan .save {
            display: inline-block;
            margin-top: 14px;
            padding: 4px 10px;
            border-radius: 8px;
            background: var(--save-bg);
            color: var(--save-ink);
            font-size: 13px;
            font-weight: 700;
        }

        .plan.is-best {
            background: var(--inverse);
            color: var(--on-inverse);
            border-color: var(--orange);
        }

        .plan.is-best h3,
        .plan.is-best .per {
            color: var(--on-inverse-muted);
        }

        .plan.is-best .save {
            background: rgba(255, 174, 31, .16);
            color: var(--amber);
        }

        .plans-note {
            margin: 20px 0 0;
            color: var(--muted);
            font-size: 14.5px;
            max-width: 640px;
        }

        /* tanya jawab */
        .faq {
            display: grid;
            gap: 10px;
            max-width: 820px;
        }

        .faq details {
            border-radius: 16px;
            background: var(--surface);
            border: 1px solid var(--line);
        }

        .faq summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 22px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            list-style: none;
        }

        .faq summary::-webkit-details-marker {
            display: none;
        }

        .faq summary::after {
            content: '+';
            font-size: 22px;
            font-weight: 500;
            color: var(--muted);
            transition: transform .2s ease;
        }

        .faq details[open] summary::after {
            transform: rotate(45deg);
        }

        .faq details p {
            margin: 0;
            padding: 0 22px 20px;
            color: var(--muted);
            font-size: 15px;
        }

        /* ajakan akhir */
        .final {
            padding: 0 0 88px;
        }

        .final-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
            padding: 40px 44px;
            border-radius: 28px;
            background: var(--orange);
            color: #fff;
        }

        .final-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .final-box h2 {
            margin: 0 0 6px;
            font-size: clamp(24px, 3vw, 34px);
            line-height: 1.15;
            letter-spacing: -.02em;
        }

        .final-box p {
            margin: 0;
            color: rgba(255, 255, 255, .88);
        }

        .final-box .btn {
            background: #15181d;
            border-color: #15181d;
            color: #fff;
        }

        .final-box .btn.is-ghost {
            color: #fff;
        }

        .final-box .btn.is-ghost {
            background: transparent;
            border-color: rgba(255, 255, 255, .5);
        }

        footer {
            padding: 28px 0 40px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 14px;
        }

        footer .wrap {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* ---------------- responsif ---------------- */
        @media (max-width: 960px) {
            .hero .wrap {
                grid-template-columns: 1fr;
                gap: 44px;
            }

            .hero h1 {
                max-width: 20ch;
                font-size: clamp(36px, 7vw, 56px);
            }

            .lead {
                max-width: 56ch;
            }

            .counter {
                width: 100%;
                max-width: 560px;
                margin: 0 auto;
            }

            .fix {
                grid-template-columns: 48px minmax(0, 1fr);
            }

            .fix-icon {
                width: 48px;
                height: 48px;
                border-radius: 14px;
            }

            .fix-demo {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 720px) {
            .wrap {
                padding: 0 16px;
            }

            .nav-links a.link {
                display: none;
            }

            .hero {
                padding: 36px 0 64px;
            }

            .lead {
                font-size: 16.5px;
            }

            .hero-actions .btn {
                flex: 1 1 100%;
            }

            section {
                padding: 64px 0;
            }

            .steps {
                grid-template-columns: 1fr;
            }

            .step,
            .step+.step {
                padding: 22px 0;
                border-left: 0;
                border-bottom: 1px solid var(--line);
            }

            .counter {
                padding: 18px 16px 0;
                border-radius: 24px;
            }

            .queue {
                margin: 0 -16px;
            }

            .final-box {
                padding: 30px 24px;
            }

            .final-box .btn {
                flex: 1 1 100%;
            }
        }

        @media (min-width: 961px) and (max-height: 820px) {
            .hero {
                padding: 40px 0 64px;
            }

            .hero h1 {
                font-size: clamp(38px, 4.6vw, 56px);
                margin: 16px 0 14px;
            }

            .receipt {
                font-size: 12px;
                line-height: 1.6;
                padding: 18px 18px 24px;
            }
        }

        @media (max-width: 380px) {
            .nav .btn {
                padding: 0 12px;
            }

            .brand span {
                max-width: 110px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .receipt {
                margin: -8px 6px 0;
                padding: 20px 14px 26px;
                font-size: 11.5px;
            }

            .counter-hint {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .receipt {
                animation: none;
            }

            .btn,
            .switch,
            .switch::after {
                transition: none;
            }
        }
    </style>
</head>

<body>
    <header class="nav">
        <div class="wrap">
            <a href="{{ url('/') }}" class="brand">
                @if ($s->logo)
                    <img src="{{ $s->logo_url }}" alt="">
                @else
                    <span class="brand-mark"><i data-lucide="receipt-text"></i></span>
                @endif
                <span>{{ $appName }}</span>
            </a>
            <nav class="nav-links" aria-label="Menu">
                <a href="#fitur" class="link">Fitur</a>
                @if ($plans->isNotEmpty())
                    <a href="#harga" class="link">Harga</a>
                @endif
                <a href="#tanya" class="link">Tanya jawab</a>
                <button type="button" class="theme-btn" id="themeBtn" aria-label="Ganti mode terang / gelap">
                    <i data-lucide="moon" class="i-moon"></i><i data-lucide="sun" class="i-sun"></i>
                </button>
                <a href="{{ route('login') }}" class="btn">Masuk</a>
                @if ($canRegister)
                    <a href="{{ route('register') }}" class="btn btn-primary">Daftar</a>
                @endif
            </nav>
        </div>
    </header>

    <main>
        {{-- ===================== HERO ===================== --}}
        <section class="hero">
            <div class="wrap">
                <div>
                    <span class="tagline"><span
                            class="dot"></span>{{ $s->store_tagline ?: 'Aplikasi kasir Android untuk toko' }}</span>
                    <h1>{!! $s->hero_title ?: 'Kasir toko yang tetap jalan walau internet mati' !!}</h1>
                    <p class="lead">
                        {{ $s->hero_subtitle ?: 'Jual per batang, bungkus, atau slop. Stok expire terpantau, laporan bisa dilihat dari mana saja. Coba gratis ' . $trialDays . ' hari.' }}
                    </p>

                    <div class="hero-actions">
                        @if ($canRegister)
                            <a href="{{ route('register') }}" class="btn btn-primary btn-lg"><i
                                    data-lucide="store"></i>{{ $s->hero_button ?: 'Daftarkan toko' }}</a>
                        @endif
                        @if ($wa)
                            <a href="https://wa.me/{{ $wa }}" class="btn btn-lg" target="_blank"
                                rel="noopener"><i data-lucide="message-circle"></i>Tanya via WhatsApp</a>
                        @endif
                    </div>

                    <ul class="hero-facts">
                        <li><i data-lucide="smartphone"></i>Android</li>
                        <li><i data-lucide="printer"></i>Printer struk Bluetooth</li>
                        <li><i data-lucide="wifi-off"></i>Offline sampai {{ $offlineText }}</li>
                    </ul>
                </div>

                {{-- meja kasir: coba matikan internet --}}
                <div class="counter" id="counter" aria-label="Contoh struk dari aplikasi kasir">
                    <div class="counter-top">
                        <span class="net">
                            <span class="net-state"><i></i><span id="netLabel">Online</span></span>
                            <button type="button" class="switch" id="netSwitch" role="switch" aria-checked="true"
                                aria-label="Internet"></button>
                        </span>
                        <span class="counter-hint" id="netHint">Coba matikan internet</span>
                    </div>

                    <div class="printer">
                        <div class="printer-slot"></div>
                        <div class="receipt" id="receipt">
                            <div class="r-head">
                                <strong>TOKO SUMBER REJEKI</strong>
                                <span>14/10/2026 08:12 · Kasir 1</span>
                            </div>
                            <hr class="r-sep">
                            <div class="r-row"><span>Rokok A · 1 SLOP</span><span>345.000</span></div>
                            <div class="r-row"><span>Rokok A · 2 BUNGKUS</span><span>72.000</span></div>
                            <div class="r-row"><span>Susu UHT · 3 PCS</span><span>18.000</span></div>
                            <div class="r-row"><small>&nbsp;&nbsp;exp 12/11 diambil dulu</small><span></span></div>
                            <div class="r-row"><span>Air mineral · 1 DUS</span><span>45.000</span></div>
                            <hr class="r-sep">
                            <div class="r-row r-total"><span>TOTAL</span><span>480.000</span></div>
                            <div class="r-row"><span>Tunai</span><span>500.000</span></div>
                            <div class="r-row"><span>Kembali</span><span>20.000</span></div>
                            <div class="r-foot">Terima kasih, sampai jumpa lagi</div>
                            <div class="stamp-row"><span class="stamp" id="stamp">Terkirim</span></div>
                        </div>
                    </div>

                    <div class="queue" id="queue">
                        <i data-lucide="cloud-check"></i><span>Semua transaksi sudah <b>tersimpan di server</b></span>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== MASALAH → SOLUSI ===================== --}}
        <section id="fitur" class="alt">
            <div class="wrap">
                <div class="sec-head">
                    <h2>Dibuat untuk repotnya toko sehari-hari</h2>
                    <p>Bukan sekadar mesin hitung. Aplikasi ini mengurus hal-hal yang biasanya bikin stok dan laba
                        meleset.</p>
                </div>

                <div class="fixes">
                    <article class="fix is-lead">
                        <span class="fix-icon"><i data-lucide="wifi-off"></i></span>
                        <div>
                            <h3>Internet putus, kasir tetap jalan</h3>
                            <p>Transaksi tersimpan di HP dan terkirim otomatis begitu sinyal kembali. Tidak ada antrean
                                yang tertahan.</p>
                        </div>
                        <div class="fix-demo">3 transaksi menunggu<br><span class="ok">✓ terkirim 08:41</span>
                        </div>
                    </article>

                    <article class="fix">
                        <span class="fix-icon"><i data-lucide="boxes"></i></span>
                        <div>
                            <h3>Satu barang, banyak satuan</h3>
                            <p>Beli per slop, jual per slop, bungkus, atau batang. Stok dihitung otomatis — tidak perlu
                                bongkar kemasan manual.</p>
                        </div>
                        <div class="fix-demo">Stok rokok A<br><b>3 SLOP, 7 BUNGKUS, 13 BATANG</b></div>
                    </article>

                    <article class="fix">
                        <span class="fix-icon"><i data-lucide="calendar-clock"></i></span>
                        <div>
                            <h3>Barang expire terjual lebih dulu</h3>
                            <p>Kasir otomatis mengambil stok yang paling dekat tanggal expire. Ada peringatan sebelum
                                kedaluwarsa.</p>
                        </div>
                        <div class="fix-demo">Susu UHT<br>exp 12/11 → <b>dijual dulu</b><br>exp 03/01 → menunggu</div>
                    </article>

                    <article class="fix">
                        <span class="fix-icon"><i data-lucide="scan-barcode"></i></span>
                        <div>
                            <h3>Scan bungkus, scan slop</h3>
                            <p>Tiap satuan bisa punya barcode sendiri. Scan kemasan, satuan dan harganya langsung benar.
                            </p>
                        </div>
                        <div class="fix-demo">899••••••6 → <b>1 BUNGKUS</b><br>899••••••3 → <b>1 SLOP</b></div>
                    </article>

                    <article class="fix">
                        <span class="fix-icon"><i data-lucide="hand-coins"></i></span>
                        <div>
                            <h3>Tahu untung yang sebenarnya</h3>
                            <p>Modal diambil dari harga beli tiap kedatangan barang. Piutang, tempo, dan hutang ke
                                supplier tercatat rapi.</p>
                        </div>
                        <div class="fix-demo">Omzet hari ini 2.450.000<br>Laba <span class="ok">+386.500</span>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        {{-- ===================== MULAI ===================== --}}
        <section>
            <div class="wrap">
                <div class="sec-head">
                    <h2>Mulai pakai hari ini</h2>
                    <p>Satu akun untuk satu toko. Tidak perlu kartu kredit untuk mencoba.</p>
                </div>
                <ol class="steps">
                    <li class="step">
                        <span class="step-no">Langkah 1</span>
                        <h3>Daftarkan toko</h3>
                        <p>Isi nama toko dan akun pemilik dari aplikasi atau web.</p>
                    </li>
                    <li class="step">
                        <span class="step-no">Langkah 2</span>
                        <h3>Tunggu persetujuan</h3>
                        <p>Setelah disetujui, uji coba {{ $trialDays }} hari langsung berjalan.</p>
                    </li>
                    <li class="step">
                        <span class="step-no">Langkah 3</span>
                        <h3>Mulai jualan</h3>
                        <p>Masukkan produk (bisa import Excel), sambungkan printer, dan layani pembeli.</p>
                    </li>
                </ol>
            </div>
        </section>

        {{-- ===================== HARGA ===================== --}}
        @if ($plans->isNotEmpty())
            <section id="harga" class="alt">
                <div class="wrap">
                    <div class="sec-head">
                        <h2>Harga sederhana</h2>
                        <p>Semua fitur ada di setiap paket. Pilih yang paling pas dengan cara bayar Anda.</p>
                    </div>
                    <div class="plans">
                        @foreach ($plans as $plan)
                            @php
                                $months = (int) $plan->duration_months;
                                $save = $monthly && $months > 1 ? $monthly->price * $months - $plan->price : 0;
                                $best = $save > 0 && $plan->is($plans->sortByDesc('duration_months')->first());
                            @endphp
                            <div class="plan {{ $best ? 'is-best' : '' }}">
                                <h3>{{ $plan->name }}</h3>
                                <div class="price">{{ $rp($plan->price) }}</div>
                                <div class="per">per {{ $months == 1 ? 'bulan' : $months . ' bulan' }}</div>
                                @if ($save > 0)
                                    <span class="save">Hemat {{ $rp($save) }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <p class="plans-note">Bayar lewat transfer bank, lalu kirim bukti dari aplikasi. Masa aktif
                        bertambah setelah pembayaran dikonfirmasi.</p>
                </div>
            </section>
        @endif

        {{-- ===================== TANYA JAWAB ===================== --}}
        <section id="tanya">
            <div class="wrap">
                <div class="sec-head">
                    <h2>Yang sering ditanyakan</h2>
                </div>
                <div class="faq">
                    <details>
                        <summary>Berapa lama kasir bisa dipakai tanpa internet?</summary>
                        <p>Sampai {{ $offlineText }} sejak terakhir tersambung. Peringatan muncul lebih awal, dan
                            cukup sambungkan internet sebentar supaya kasir bisa dipakai lagi. Transaksi yang sudah
                            tersimpan tidak pernah hilang.</p>
                    </details>
                    <details>
                        <summary>Printer apa yang bisa dipakai?</summary>
                        <p>Printer struk thermal Bluetooth ukuran 58 mm atau 80 mm yang umum dijual di pasaran.</p>
                    </details>
                    <details>
                        <summary>Apakah bisa dipakai di beberapa HP?</summary>
                        <p>Satu toko punya satu HP kasir aktif. Pindah ke HP lain cukup sekali ketuk dari aplikasi.
                            Laporan bisa dilihat di web dari HP atau laptop mana saja.</p>
                    </details>
                    <details>
                        <summary>Bagaimana kalau masa aktif habis?</summary>
                        <p>Data tetap aman dan bisa dilihat. Transaksi baru dibuka lagi setelah perpanjangan
                            dikonfirmasi. Langganan berbayar mendapat masa tenggang 7 hari.</p>
                    </details>
                    <details>
                        <summary>Produk saya ratusan, harus diketik satu per satu?</summary>
                        <p>Tidak. Produk bisa dimasukkan sekaligus dengan file Excel, lengkap dengan harga dan stok
                            awal.</p>
                    </details>
                </div>
            </div>
        </section>

        {{-- ===================== AJAKAN AKHIR ===================== --}}
        <section class="final">
            <div class="wrap">
                <div class="final-box">
                    <div>
                        <h2>Coba gratis {{ $trialDays }} hari</h2>
                        <p>Daftarkan toko sekarang. Uji coba dimulai setelah toko disetujui.</p>
                    </div>
                    <div class="final-actions">
                        @if ($canRegister)
                            <a href="{{ route('register') }}" class="btn btn-lg"><i
                                    data-lucide="store"></i>Daftarkan toko</a>
                        @endif
                        <a href="{{ route('login') }}" class="btn btn-lg is-ghost">Masuk</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="wrap">
            <span>{{ $s->copyright ?: '© ' . date('Y') . ' ' . $appName }}</span>
            <span>
                @if ($s->phone)
                    {{ $s->phone }}
                @endif
                @if ($s->phone && $s->email)
                    &nbsp;|&nbsp;
                @endif
                @if ($s->email)
                    {{ $s->email }}
                @endif
            </span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    <script>
        window.lucide?.createIcons();

        // Mode terang / gelap: ikut sistem, bisa diganti lewat tombol (diingat di browser)
        (() => {
            const btn = document.getElementById('themeBtn');
            if (!btn) return;
            const root = document.documentElement;
            const isDark = () => root.dataset.theme ?
                root.dataset.theme === 'dark' :
                window.matchMedia('(prefers-color-scheme: dark)').matches;
            const sync = () => {
                // ikon mengikuti mode efektif (termasuk saat mengikuti sistem)
                if (!root.dataset.theme) root.dataset.themeAuto = isDark() ? 'dark' : 'light';
                btn.setAttribute('aria-pressed', isDark() ? 'true' : 'false');
            };
            btn.addEventListener('click', () => {
                const next = isDark() ? 'light' : 'dark';
                root.dataset.theme = next;
                try {
                    localStorage.setItem('landing-theme', next);
                } catch (e) {}
                sync();
            });
            sync();
        })();

        // Demo: matikan internet → struk tetap tercetak, transaksi menunggu dikirim
        (() => {
            const counter = document.getElementById('counter');
            const sw = document.getElementById('netSwitch');
            if (!counter || !sw) return;
            const label = document.getElementById('netLabel');
            const hint = document.getElementById('netHint');
            const stamp = document.getElementById('stamp');
            const queue = document.getElementById('queue');
            const receipt = document.getElementById('receipt');

            const render = (online) => {
                counter.classList.toggle('is-offline', !online);
                sw.setAttribute('aria-checked', online ? 'true' : 'false');
                label.textContent = online ? 'Online' : 'Offline';
                hint.textContent = online ? 'Coba matikan internet' : 'Kasir tetap jalan';
                stamp.textContent = online ? 'Terkirim' : 'Tersimpan di HP';
                queue.innerHTML = online ?
                    '<i data-lucide="cloud-check"></i><span>Semua transaksi sudah <b>tersimpan di server</b></span>' :
                    '<i data-lucide="hourglass"></i><span><b>3 transaksi</b> menunggu, terkirim otomatis saat online</span>';
                window.lucide?.createIcons();

                // cetak ulang struk (kecuali pengguna memilih gerak dikurangi)
                if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    receipt.style.animation = 'none';
                    void receipt.offsetWidth;
                    receipt.style.animation = '';
                }
            };

            sw.addEventListener('click', () => render(sw.getAttribute('aria-checked') !== 'true'));
        })();
    </script>
</body>

</html>
