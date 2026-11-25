@php
    $s = $setting;
    $appName = $s->store_name ?: config('app.name', 'Kasir');
    $trialDays = $s->trial_days ?: 3;
    $offlineHours = $s->offline_limit_hours ?: 48;
    $offlineText = $offlineHours % 24 === 0 ? $offlineHours / 24 . ' hari' : $offlineHours . ' jam';
    $wa = $s->whatsapp ? preg_replace('/\D/', '', preg_replace('/^0/', '62', $s->whatsapp)) : null;
    $rp = fn($n) => 'Rp' . number_format((float) $n, 0, ',', '.');
    $canRegister = Route::has('register');

    // tautan aplikasi di Google Play (bisa ditimpa lewat Pengaturan bila tersedia)
    $playStoreUrl = $s->playstore_url ?: 'https://play.google.com/store/apps/details?id=id.zdtech.mycashier&hl=id';

    // hemat paket tahunan dibanding bayar bulanan 12x
    $monthly = $plans->firstWhere('duration_months', 1);

    // promo landing dengan potongan terbesar untuk sebuah paket (null bila tak ada)
    $bestPromo = function ($plan) use ($promos) {
        if (! isset($promos) || $promos->isEmpty()) {
            return null;
        }

        return $promos
            ->filter(fn($p) => $p->appliesToPlan($plan->id) && $p->discountFor((int) $plan->price) > 0)
            ->sortByDesc(fn($p) => $p->discountFor((int) $plan->price))
            ->first();
    };
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
            --line: #e8e3da;
            --fog: #f1eee8;
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
            --bg: #f6f4ef;
            --surface: #ffffff;
            --hover: rgba(21, 24, 29, .05);
            --nav-bg: rgba(246, 244, 239, .78);
            /* mode terang premium: kertas hangat, garis tipis, bayangan berlapis */
            --card-shadow: 0 1px 1px rgba(21, 24, 29, .04), 0 12px 32px -18px rgba(21, 24, 29, .28);
            --card-shadow-hover: 0 1px 1px rgba(21, 24, 29, .05), 0 22px 44px -20px rgba(21, 24, 29, .34);
            --hero-bg: radial-gradient(900px 480px at 88% -10%, rgba(255, 106, 26, .16), transparent 60%),
                radial-gradient(700px 420px at -5% 100%, rgba(15, 157, 132, .10), transparent 60%);
            --alt-bg: linear-gradient(180deg, #ffffff, #fbfaf7);
            --icon-bg: linear-gradient(135deg, #fff3ea, #ffe6d4);
            --icon-ink: #d9500a;
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
                --card-shadow: 0 1px 0 rgba(255, 255, 255, .03) inset;
                --card-shadow-hover: 0 18px 40px -22px rgba(0, 0, 0, .9);
                --hero-bg: radial-gradient(900px 480px at 88% -10%, rgba(255, 106, 26, .14), transparent 60%);
                --alt-bg: var(--surface);
                --icon-bg: #0f1215;
                --icon-ink: #eef1f5;
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
            --card-shadow: 0 1px 0 rgba(255, 255, 255, .03) inset;
            --card-shadow-hover: 0 18px 40px -22px rgba(0, 0, 0, .9);
            --hero-bg: radial-gradient(900px 480px at 88% -10%, rgba(255, 106, 26, .14), transparent 60%);
            --alt-bg: var(--surface);
            --icon-bg: #0f1215;
            --icon-ink: #eef1f5;
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
            background: var(--hero-bg);
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
            box-shadow: var(--card-shadow);
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
            max-width: 15ch;
        }

        .hero h1 em {
            font-style: normal;
            background: linear-gradient(100deg, var(--orange), var(--amber));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
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

        /* ---------------- dua mode jualan ---------------- */
        .modes-label {
            display: block;
            margin: 26px 0 10px;
            color: var(--ink-2);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .modes {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            max-width: 560px;
        }

        .mode {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px;
            border-radius: 18px;
            border: 1.5px solid var(--line);
            background: var(--surface);
            box-shadow: var(--card-shadow);
            color: var(--ink);
            font: inherit;
            text-align: left;
            cursor: pointer;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .mode:hover {
            transform: translateY(-2px);
        }

        .mode.is-active {
            border-color: var(--orange);
            box-shadow: 0 0 0 4px rgba(255, 106, 26, .12), var(--card-shadow);
        }

        .mode:focus-visible {
            outline: 3px solid var(--orange);
            outline-offset: 2px;
        }

        .mode-ic {
            flex: none;
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 13px;
            background: var(--icon-bg);
            color: var(--icon-ink);
            transition: background .18s ease, color .18s ease;
        }

        .mode.is-active .mode-ic {
            background: linear-gradient(135deg, var(--orange), var(--amber));
            color: #fff;
        }

        .mode-ic svg {
            width: 21px;
            height: 21px;
        }

        .mode-txt strong {
            display: block;
            font-size: 15px;
            line-height: 1.3;
        }

        .mode-txt small {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .modes-note {
            display: flex;
            align-items: center;
            gap: 7px;
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .modes-note svg {
            width: 15px;
            height: 15px;
            color: var(--teal);
        }

        .r-body[hidden] {
            display: none;
        }

        /* ---------------- meja kasir (demo) ---------------- */
        /* ---- hero: slideshow HP Android ---- */
        .hero-phone-wrap {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
            padding: 8px 0 4px;
        }

        .hero-phone-wrap::before {
            content: "";
            position: absolute;
            inset: -4% 6% 12%;
            z-index: 0;
            background: radial-gradient(58% 48% at 72% 22%, rgba(255, 106, 26, .40), transparent 70%),
                radial-gradient(52% 44% at 24% 86%, rgba(255, 174, 31, .28), transparent 72%);
            filter: blur(12px);
        }

        .hero-phone {
            position: relative;
            z-index: 1;
            width: min(100%, 298px);
            padding: 10px;
            border-radius: 46px;
            background: linear-gradient(160deg, #20242b, #0b0d10);
            box-shadow: 0 1px 0 rgba(255, 255, 255, .07) inset, var(--shadow-lg);
        }

        .hp-notch {
            position: absolute;
            top: 19px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 4;
            width: 92px;
            height: 7px;
            border-radius: 99px;
            background: rgba(255, 255, 255, .22);
        }

        .hp-screen {
            position: relative;
            border-radius: 36px;
            overflow: hidden;
            background: #000;
            aspect-ratio: 108 / 226;
        }

        .hp-slide {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            transform: scale(1.05);
            transition: opacity .9s ease, transform 4.2s ease;
        }

        .hp-slide.is-active {
            opacity: 1;
            transform: scale(1);
        }

        .hp-badge {
            position: absolute;
            z-index: 3;
            top: 18px;
            right: -8px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 13px;
            border-radius: 999px;
            background: var(--surface);
            color: var(--ink);
            font-size: 12.5px;
            font-weight: 800;
            border: 1px solid var(--line);
            box-shadow: var(--card-shadow-hover);
        }

        .hp-badge i {
            width: 15px;
            height: 15px;
            color: var(--orange);
        }

        .hp-cap {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            max-width: 92%;
            padding: 10px 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, #15181d, #23272f);
            color: #fff;
            font-size: 13.5px;
            font-weight: 700;
            box-shadow: var(--card-shadow-hover);
        }

        .hp-cap i {
            width: 16px;
            height: 16px;
            color: var(--amber);
            flex: none;
        }

        .hp-dots {
            display: flex;
            gap: 8px;
            z-index: 1;
        }

        .hp-dots button {
            width: 8px;
            height: 8px;
            padding: 0;
            border: 0;
            border-radius: 99px;
            cursor: pointer;
            background: rgba(140, 146, 158, .42);
            transition: width .25s ease, background .25s ease;
        }

        .hp-dots button.is-active {
            width: 22px;
            background: var(--orange);
        }

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
            background: var(--alt-bg);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .eyebrow {
            display: inline-block;
            margin-bottom: 12px;
            color: var(--accent-text);
            font-size: 12.5px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        /* owner tenang: pantau kasir dari jauh */
        .trust-sec {
            padding: 24px 0 64px;
        }

        .trust {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 48px;
            align-items: center;
            padding: 40px;
            border-radius: 32px;
            background: var(--surface);
            border: 1px solid var(--line);
            box-shadow: var(--card-shadow);
        }

        .trust h2 {
            margin: 0 0 12px;
            font-size: clamp(28px, 3.4vw, 40px);
            line-height: 1.12;
            letter-spacing: -.03em;
            font-weight: 800;
        }

        .trust h2 em {
            font-style: normal;
            color: var(--accent-text);
        }

        .trust-lead {
            margin: 0 0 22px;
            color: var(--muted);
            font-size: 16.5px;
            line-height: 1.6;
        }

        .trust-list {
            display: grid;
            gap: 14px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .trust-list li {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
        }

        .trust-list .ti {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--icon-bg);
            color: var(--icon-ink);
        }

        .trust-list .ti svg {
            width: 17px;
            height: 17px;
        }

        .trust-list strong {
            display: block;
            font-size: 15px;
        }

        .trust-list span.td {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        /* layar owner (contoh) */
        .owner-screen {
            border-radius: 22px;
            background: linear-gradient(160deg, #1d2128, #12151a 60%);
            color: #e8ebef;
            padding: 18px;
            box-shadow: var(--shadow-lg);
        }

        .os-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
        }

        .os-top strong {
            font-size: 14px;
        }

        .os-top small {
            display: block;
            color: #8b94a3;
            font-size: 12px;
        }

        .os-live {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(52, 211, 153, .12);
            color: #34d399;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .os-live i {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #34d399;
            animation: blink 1.6s ease-in-out infinite;
        }

        @keyframes blink {
            50% {
                opacity: .3;
            }
        }

        .os-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .os-stat {
            padding: 12px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .07);
        }

        .os-stat small {
            display: block;
            color: #8b94a3;
            font-size: 11.5px;
        }

        .os-stat b {
            font-size: 18px;
            letter-spacing: -.01em;
        }

        .os-rows {
            display: grid;
            gap: 6px;
        }

        .os-row {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(255, 255, 255, .035);
            font-size: 13px;
        }

        .os-row time {
            color: #8b94a3;
            font-variant-numeric: tabular-nums;
        }

        .os-row .who {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .os-row .who small {
            color: #8b94a3;
        }

        .os-row b {
            font-variant-numeric: tabular-nums;
        }

        .os-row.is-flag {
            background: rgba(255, 174, 31, .1);
            border: 1px solid rgba(255, 174, 31, .28);
        }

        .os-flag {
            grid-column: 2 / -1;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--amber);
            font-size: 12px;
            font-weight: 600;
        }

        .os-flag svg {
            width: 14px;
            height: 14px;
        }

        .os-foot {
            margin-top: 12px;
            color: #8b94a3;
            font-size: 12px;
            text-align: center;
        }

        /* yang didapat setelah berlangganan */
        .perks-sec {
            padding-top: 24px;
        }

        .perks {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .perk {
            padding: 24px;
            border-radius: var(--radius);
            background: var(--surface);
            border: 1px solid var(--line);
            box-shadow: var(--card-shadow);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .perk:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-shadow-hover);
        }

        .perk-icon {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            border-radius: 14px;
            margin-bottom: 16px;
            background: var(--icon-bg);
            color: var(--icon-ink);
        }

        .perk-icon svg {
            width: 23px;
            height: 23px;
        }

        .perk h3 {
            margin: 0 0 6px;
            font-size: 17.5px;
            letter-spacing: -.01em;
        }

        .perk p {
            margin: 0;
            color: var(--muted);
            font-size: 14.5px;
        }

        .perk.is-hero {
            grid-column: span 2;
            grid-row: span 2;
            display: flex;
            flex-direction: column;
            background: linear-gradient(135deg, #1d2128, #12151a 70%);
            border-color: #2a3039;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .perk.is-hero::after {
            content: '';
            position: absolute;
            right: -60px;
            top: -80px;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 106, 26, .35), transparent 65%);
            pointer-events: none;
        }

        .perk.is-hero h3 {
            font-size: 22px;
        }

        .perk.is-hero p {
            color: #b3bac5;
            max-width: 46ch;
        }

        .devices {
            position: relative;
            z-index: 1;
            margin-top: auto;
            padding-top: 26px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .device {
            flex: 1 1 180px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
        }

        .device svg {
            width: 24px;
            height: 24px;
            color: var(--amber);
            flex: none;
        }

        .device b {
            display: block;
            font-size: 15px;
        }

        .device small {
            color: #8f98a5;
            font-size: 12.5px;
        }

        .device-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(15, 157, 132, .18);
            color: #4fd1b5;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .device-link svg {
            width: 14px;
            height: 14px;
        }

        .perk.is-hero .perk-icon {
            background: linear-gradient(135deg, #ff8a3d, var(--orange));
            color: #fff;
            box-shadow: 0 10px 24px -8px rgba(255, 106, 26, .6);
        }

        /* termasuk di semua paket */
        .included {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 22px 0 0;
            padding: 0;
            list-style: none;
        }

        .included li {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: var(--surface);
            border: 1px solid var(--line);
            box-shadow: var(--card-shadow);
            font-size: 14px;
            font-weight: 600;
        }

        .included svg {
            width: 16px;
            height: 16px;
            color: var(--teal);
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
            box-shadow: var(--card-shadow);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .fix:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow-hover);
        }

        .fix-icon {
            display: grid;
            place-items: center;
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: var(--icon-bg);
            color: var(--icon-ink);
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

        /* ---------------- etalase layar asli ---------------- */
        .showcase {
            background: var(--alt-bg);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .shots-hint {
            display: none;
        }

        .shots {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 36px 28px;
            margin-top: 40px;
        }

        .shot {
            display: flex;
            flex-direction: column;
        }

        .phone {
            position: relative;
            align-self: center;
            width: 100%;
            max-width: 278px;
            padding: 10px;
            border-radius: 40px;
            background: linear-gradient(160deg, #20242b, #0b0d10);
            box-shadow: 0 1px 0 rgba(255, 255, 255, .06) inset, var(--card-shadow-hover);
            transition: transform .25s ease;
        }

        .shot:hover .phone {
            transform: translateY(-6px);
        }

        .phone::before {
            content: "";
            position: absolute;
            top: 17px;
            left: 50%;
            transform: translateX(-50%);
            width: 88px;
            height: 6px;
            border-radius: 99px;
            background: rgba(255, 255, 255, .22);
            z-index: 2;
        }

        .phone img {
            display: block;
            width: 100%;
            border-radius: 31px;
            background: #fff;
        }

        .shot-cap {
            margin-top: 22px;
            padding: 0 4px;
        }

        .shot-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 11px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .02em;
            background: rgba(255, 106, 26, .12);
            color: var(--orange-deep);
        }

        .shot-tag i {
            width: 14px;
            height: 14px;
        }

        .shot h3 {
            margin: 0 0 7px;
            font-size: 19px;
            line-height: 1.25;
            letter-spacing: -.01em;
        }

        .shot p {
            margin: 0;
            color: var(--muted);
            font-size: 14.5px;
            line-height: 1.55;
        }

        .showcase-tie {
            margin-top: 48px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 26px 30px;
            border-radius: 24px;
            background: linear-gradient(135deg, #15181d, #23272f);
            color: var(--on-inverse);
            box-shadow: var(--shadow-lg);
        }

        .showcase-tie .tie-txt strong {
            display: block;
            font-size: 19px;
            letter-spacing: -.01em;
        }

        .showcase-tie .tie-txt span {
            display: block;
            margin-top: 5px;
            color: var(--on-inverse-muted);
            font-size: 14px;
        }

        .showcase-tie .tie-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .showcase-tie .btn:not(.btn-primary) {
            background: rgba(255, 255, 255, .12);
            color: #fff;
            border-color: rgba(255, 255, 255, .22);
        }

        .showcase-tie .btn:not(.btn-primary):hover {
            background: rgba(255, 255, 255, .2);
        }

        @media (max-width: 860px) {
            .shots {
                grid-template-columns: none;
                grid-auto-flow: column;
                grid-auto-columns: 76%;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                margin: 34px -16px 0;
                padding: 4px 16px 18px;
                scrollbar-width: none;
            }

            .shots::-webkit-scrollbar {
                display: none;
            }

            .shot {
                scroll-snap-align: center;
            }

            .phone {
                max-width: 236px;
            }

            .shots-hint {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                margin: 16px 0 0;
                color: var(--muted);
                font-size: 13.5px;
                font-weight: 600;
            }

            .shots-hint i {
                width: 16px;
                height: 16px;
            }

            .showcase-tie {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
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
            box-shadow: var(--card-shadow);
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

        .plan .price-was {
            margin: 10px 0 0;
            font-size: 19px;
            font-weight: 600;
            line-height: 1;
            color: var(--muted);
            text-decoration: line-through;
            text-decoration-color: var(--orange);
            text-decoration-thickness: 2px;
        }

        .plan.has-promo .price {
            margin: 2px 0 2px;
            color: var(--orange);
        }

        .plan .promo-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 14px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(255, 106, 26, .12);
            color: var(--orange);
            font-size: 13px;
            font-weight: 700;
        }

        .plan .promo-chip i {
            width: 15px;
            height: 15px;
        }

        .plan .promo-chip b {
            font-weight: 800;
            letter-spacing: .02em;
        }

        .plan.is-best {
            background: linear-gradient(150deg, #1d2128, #12151a 70%);
            color: var(--on-inverse);
            border-color: var(--orange);
            box-shadow: 0 24px 48px -24px rgba(255, 106, 26, .55);
        }

        .plan.is-best h3,
        .plan.is-best .per {
            color: var(--on-inverse-muted);
        }

        .plan.is-best .save {
            background: rgba(255, 174, 31, .16);
            color: var(--amber);
        }

        .plan.is-best .price-was {
            color: var(--on-inverse-muted);
            text-decoration-color: var(--amber);
        }

        .plan.is-best.has-promo .price {
            color: var(--amber);
        }

        .plan.is-best .promo-chip {
            background: rgba(255, 174, 31, .16);
            color: var(--amber);
        }

        .plans-note {
            margin: 20px 0 0;
            color: var(--muted);
            font-size: 14.5px;
            max-width: 640px;
        }

        /* tombol Google Play */
        .btn-play {
            gap: 10px;
            background: #0b0d10;
            color: #fff;
            border-color: #0b0d10;
        }

        .btn-play:hover {
            background: #000;
            border-color: #000;
        }

        .btn-play svg {
            width: 22px;
            height: 22px;
            flex: none;
        }

        .btn-play .btn-play-txt {
            display: flex;
            flex-direction: column;
            line-height: 1.04;
            text-align: left;
        }

        .btn-play .btn-play-txt small {
            font-size: 10px;
            font-weight: 600;
            opacity: .82;
            letter-spacing: .02em;
        }

        .btn-play .btn-play-txt strong {
            font-size: 15px;
            font-weight: 700;
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .btn-play {
                background: #fff;
                color: #0b0d10;
                border-color: #fff;
            }

            :root:not([data-theme="light"]) .btn-play:hover {
                background: #eceff3;
                border-color: #eceff3;
            }
        }

        :root[data-theme="dark"] .btn-play {
            background: #fff;
            color: #0b0d10;
            border-color: #fff;
        }

        :root[data-theme="dark"] .btn-play:hover {
            background: #eceff3;
            border-color: #eceff3;
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
            box-shadow: var(--card-shadow);
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
            background: radial-gradient(500px 260px at 100% 0%, rgba(255, 174, 31, .55), transparent 60%),
                linear-gradient(135deg, #ff7a2e, #e2530c);
            color: #fff;
            box-shadow: 0 30px 60px -30px rgba(226, 83, 12, .65);
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

        .final-box h2 em {
            font-style: normal;
            background: linear-gradient(transparent 62%, rgba(255, 255, 255, .38) 0);
            padding: 0 2px;
        }

        .final-box .final-promo {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .18);
            color: #fff;
            font-size: 13.5px;
            font-weight: 700;
        }

        .final-box .final-promo i {
            width: 16px;
            height: 16px;
        }

        .final-box .final-promo b {
            letter-spacing: .03em;
            text-transform: uppercase;
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
            .perks {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .perk.is-hero {
                grid-row: auto;
            }

            .perk:last-child {
                grid-column: span 2;
            }

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

            .trust {
                grid-template-columns: 1fr;
                gap: 32px;
                padding: 28px;
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
            .perks {
                grid-template-columns: 1fr;
            }

            .perk.is-hero,
            .perk:last-child {
                grid-column: auto;
            }

            .device-link {
                width: 100%;
                justify-content: center;
            }

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

            .modes {
                grid-template-columns: 1fr;
            }

            .trust {
                padding: 22px 18px;
                border-radius: 24px;
            }

            .owner-screen {
                padding: 14px;
            }

            .os-row {
                grid-template-columns: 40px minmax(0, 1fr) auto;
                padding: 9px 10px;
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

        /* ---------------- pita promo (paling atas) ---------------- */
        .promo-bar {
            position: relative;
            z-index: 60;
            background: linear-gradient(95deg, #ff6a1a, #ff8a3d);
            color: #fff;
            font-size: 13.5px;
        }

        .promo-bar-inner {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 9px 0;
        }

        .promo-track {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px 18px;
        }

        .promo-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            line-height: 1.3;
        }

        .promo-item svg {
            width: 16px;
            height: 16px;
            flex: none;
        }

        .promo-item b {
            font-weight: 800;
        }

        .promo-item code {
            font-family: 'DM Mono', ui-monospace, monospace;
            font-weight: 700;
            letter-spacing: .04em;
            background: rgba(255, 255, 255, .22);
            border: 1px dashed rgba(255, 255, 255, .6);
            padding: 1px 8px;
            border-radius: 7px;
        }

        .promo-item small {
            opacity: .85;
            font-size: 12px;
        }

        .promo-cta {
            flex: none;
            background: #fff;
            color: #c2410c;
            font-weight: 800;
            font-size: 12.5px;
            padding: 7px 14px;
            border-radius: 999px;
            text-decoration: none;
            white-space: nowrap;
            transition: transform .15s ease;
        }

        .promo-cta:hover {
            transform: translateY(-1px);
        }

        .promo-close {
            flex: none;
            background: transparent;
            border: 0;
            color: #fff;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            opacity: .85;
            padding: 0 2px;
        }

        .promo-close:hover {
            opacity: 1;
        }

        @media (max-width: 640px) {
            .promo-bar-inner {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .promo-cta {
                order: 3;
            }
        }

    </style>
</head>

<body>
    {{-- ===================== PITA PROMO (otomatis dari kode promo aktif) ===================== --}}
    @if (isset($promos) && $promos->isNotEmpty())
        <div class="promo-bar" id="promoBar" data-promo="{{ $promos->pluck('code')->join('-') }}-{{ optional($promos->first()->ends_at)->format('Ymd') }}">
            <div class="wrap promo-bar-inner">
                <div class="promo-track">
                    @foreach ($promos as $promo)
                        <span class="promo-item">
                            <i data-lucide="party-popper"></i>
                            <span><b>{{ $promo->description ?: $promo->label() }}</b>
                                — pakai kode <code>{{ $promo->code }}</code>
                                <small>s/d {{ $promo->ends_at->translatedFormat('d M Y') }}</small></span>
                        </span>
                    @endforeach
                </div>
                @if ($canRegister)
                    <a href="{{ route('register') }}" class="promo-cta">Daftar &amp; klaim</a>
                @endif
                <button type="button" class="promo-close" id="promoClose" aria-label="Tutup promo">&times;</button>
            </div>
        </div>
    @endif

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
                            class="dot"></span>{{ $s->store_tagline ?: 'Aplikasi kasir Android untuk toko sembako & cafe' }}</span>
                    <h1>{!! $s->hero_title ?: 'Kasir toko &amp; cafe yang tetap jalan <em>walau internet mati</em>' !!}</h1>
                    <p class="lead">
                        {{ $s->hero_subtitle ?: 'Satu aplikasi untuk toko sembako dan cafe. Stok, barang expire, dan omzet bisa dicek dari HP atau laptop di mana saja. Coba gratis ' . $trialDays . ' hari, tanpa kartu kredit.' }}
                    </p>

                    {{-- dua mode jualan: klik untuk melihat contoh struknya --}}
                    <span class="modes-label" id="modesLabel">Pilih mode sesuai usaha Anda</span>
                    <div class="modes" role="tablist" aria-labelledby="modesLabel">
                        <button type="button" class="mode is-active" data-mode="sembako" role="tab"
                            aria-selected="true" aria-controls="receipt">
                            <span class="mode-ic"><i data-lucide="shopping-basket"></i></span>
                            <span class="mode-txt">
                                <strong>Mode Toko Sembako</strong>
                                <small>Eceran &amp; grosir: batang, bungkus, slop, dus. Scan barcode, stok
                                    otomatis berkurang.</small>
                            </span>
                        </button>
                        <button type="button" class="mode" data-mode="cafe" role="tab" aria-selected="false"
                            aria-controls="receipt">
                            <span class="mode-ic"><i data-lucide="coffee"></i></span>
                            <span class="mode-txt">
                                <strong>Mode Cafe</strong>
                                <small>Pesanan per meja, cetak checker dapur, bayar saat tamu selesai.</small>
                            </span>
                        </button>
                    </div>
                    <p class="modes-note"><i data-lucide="repeat"></i>Ganti mode kapan saja dari dashboard, tanpa
                        biaya tambahan.</p>

                    <div class="hero-actions">
                        @if ($canRegister)
                            <a href="{{ route('register') }}" class="btn btn-primary btn-lg"><i
                                    data-lucide="store"></i>{{ $s->hero_button ?: 'Daftarkan toko' }}</a>
                        @endif
                        @if ($playStoreUrl)
                            <a href="{{ $playStoreUrl }}" class="btn btn-lg btn-play" target="_blank"
                                rel="noopener" aria-label="Download di Google Play">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M3.6 2.2C3.4 2.4 3.3 2.8 3.3 3.3v17.4c0 .5.1.9.4 1.1l.1.1 9.7-9.7v-.2L3.6 2.2z"
                                        fill="#00C3FF" />
                                    <path d="M17 15.3l-3.2-3.2v-.2L17 8.7l.1.1 3.9 2.2c1.1.6 1.1 1.7 0 2.3l-4 2z"
                                        fill="#FFCE00" />
                                    <path d="M17.1 15.2L13.8 12 3.6 22.2c.4.4 1 .4 1.7 0l11.8-6.7z" fill="#00E676" />
                                    <path d="M17.1 8.8L5.3 2.1c-.7-.4-1.3-.4-1.7 0L13.8 12l3.3-3.2z" fill="#FF3D47" />
                                </svg>
                                <span class="btn-play-txt"><small>Download di</small><strong>Google Play</strong></span>
                            </a>
                        @endif
                        @if ($wa)
                            <a href="https://wa.me/{{ $wa }}" class="btn btn-lg" target="_blank"
                                rel="noopener"><i data-lucide="message-circle"></i>Tanya via WhatsApp</a>
                        @endif
                    </div>

                    <ul class="hero-facts">
                        <li><i data-lucide="eye"></i>Owner pantau kasir dari jauh</li>
                        <li><i data-lucide="monitor-smartphone"></i>1 akun: Web Admin + Android Kasir</li>
                        <li><i data-lucide="printer"></i>Printer struk Bluetooth</li>
                        <li><i data-lucide="wifi-off"></i>Offline sampai {{ $offlineText }}</li>
                        <li><i data-lucide="scan-barcode"></i>Scan barcode pakai kamera HP</li>
                        <li><i data-lucide="qr-code"></i>Bayar tunai atau QRIS</li>
                    </ul>
                </div>

                {{-- slideshow HP Android: kasir ini dipakai lewat Android --}}
                <div class="hero-phone-wrap" id="heroShow" aria-label="Cuplikan aplikasi kasir Android">
                    <div class="hero-phone">
                        <span class="hp-badge"><i data-lucide="smartphone"></i>Dipakai di Android</span>
                        <span class="hp-notch"></span>
                        <div class="hp-screen">
                            <img class="hp-slide is-active" src="{{ asset('images/landing/01-ganti-hp.png') }}"
                                alt="Ganti HP kasir sekali ketuk" data-cap="Ganti HP kasir sekali ketuk">
                            <img class="hp-slide" src="{{ asset('images/landing/02-jual-kilo.png') }}"
                                alt="Jual per kilo, bebas desimal" data-cap="Jual per kilo, bebas desimal" loading="lazy">
                            <img class="hp-slide" src="{{ asset('images/landing/03-tempo-piutang.png') }}"
                                alt="Catat utang pelanggan otomatis" data-cap="Catat utang otomatis" loading="lazy">
                            <img class="hp-slide" src="{{ asset('images/landing/04-multi-satuan.png') }}"
                                alt="Satu produk banyak satuan" data-cap="1 produk, banyak satuan" loading="lazy">
                            <img class="hp-slide" src="{{ asset('images/landing/05-meja-cafe.png') }}"
                                alt="Kelola meja cafe" data-cap="Kelola meja cafe" loading="lazy">
                            <img class="hp-slide" src="{{ asset('images/landing/06-bayar-pesanan.png') }}"
                                alt="Simpan dan bayar pesanan" data-cap="Simpan &amp; bayar pesanan" loading="lazy">
                        </div>
                    </div>
                    <span class="hp-cap"><i data-lucide="smartphone"></i><span id="hpCapTxt">Ganti HP kasir sekali
                            ketuk</span></span>
                    <div class="hp-dots" id="hpDots"></div>
                </div>
            </div>
        </section>

        {{-- ===================== ETALASE LAYAR ASLI ===================== --}}
        <section class="showcase">
            <div class="wrap">
                <div class="sec-head">
                    <span class="eyebrow">Intip isi aplikasinya</span>
                    <h2>Ini layar aslinya, <em>bukan sekadar janji</em></h2>
                    <p>Geser dan lihat sendiri bagaimana My Cashier membereskan hal yang paling bikin repot — dari
                        jual kiloan sampai kelola meja cafe.</p>
                </div>
                <p class="shots-hint"><i data-lucide="move-horizontal"></i>Geser untuk lihat lebih banyak</p>

                <div class="shots">
                    <article class="shot">
                        <div class="phone"><img src="{{ asset('images/landing/01-ganti-hp.png') }}"
                                alt="Pindahkan kasir ke HP lain" loading="lazy"></div>
                        <div class="shot-cap">
                            <span class="shot-tag"><i data-lucide="shield-check"></i>Anti bocor</span>
                            <h3>Ganti HP kasir sekali ketuk</h3>
                            <p>HP hilang atau diganti? Pindahkan kasir ke HP baru dalam sedetik. HP lama otomatis
                                berhenti transaksi — tidak ada dobel, tidak ada celah.</p>
                        </div>
                    </article>

                    <article class="shot">
                        <div class="phone"><img src="{{ asset('images/landing/02-jual-kilo.png') }}"
                                alt="Jual per kilogram dengan desimal" loading="lazy"></div>
                        <div class="shot-cap">
                            <span class="shot-tag"><i data-lucide="scale"></i>Tanpa kalkulator</span>
                            <h3>Jual per kilo, bebas desimal</h3>
                            <p>Beras, gula, bumbu — ketik 0,5 atau 1,7 kg dan totalnya langsung muncul. Timbang,
                                ketik, selesai.</p>
                        </div>
                    </article>

                    <article class="shot">
                        <div class="phone"><img src="{{ asset('images/landing/03-tempo-piutang.png') }}"
                                alt="Penjualan tempo dan piutang" loading="lazy"></div>
                        <div class="shot-cap">
                            <span class="shot-tag"><i data-lucide="notebook-pen"></i>Auto-catat</span>
                            <h3>Kasih utang tanpa takut lupa</h3>
                            <p>Penjualan tempo langsung jadi piutang: siapa, berapa, dan kapan jatuh tempo. Cicilan
                                dan pelunasan tercatat rapi.</p>
                        </div>
                    </article>

                    <article class="shot">
                        <div class="phone"><img src="{{ asset('images/landing/04-multi-satuan.png') }}"
                                alt="Satu produk banyak satuan" loading="lazy"></div>
                        <div class="shot-cap">
                            <span class="shot-tag"><i data-lucide="boxes"></i>Stok klop</span>
                            <h3>Satu produk, banyak satuan</h3>
                            <p>Batang, bungkus, slop — jual satuan mana pun, stok berkurang otomatis dan tetap benar
                                sampai batang terakhir.</p>
                        </div>
                    </article>

                    <article class="shot">
                        <div class="phone"><img src="{{ asset('images/landing/05-meja-cafe.png') }}"
                                alt="Tampilan meja cafe" loading="lazy"></div>
                        <div class="shot-cap">
                            <span class="shot-tag"><i data-lucide="coffee"></i>Mode Cafe</span>
                            <h3>Kelola meja seperti POS mahal</h3>
                            <p>Lihat semua meja dalam satu layar: mana kosong, mana terisi, berapa okupansi. Buka
                                meja cukup sekali ketuk.</p>
                        </div>
                    </article>

                    <article class="shot">
                        <div class="phone"><img src="{{ asset('images/landing/06-bayar-pesanan.png') }}"
                                alt="Tambah dan bayar pesanan meja" loading="lazy"></div>
                        <div class="shot-cap">
                            <span class="shot-tag"><i data-lucide="receipt-text"></i>Pesanan aman</span>
                            <h3>Simpan pesanan, bayar belakangan</h3>
                            <p>Tamu pesan dulu, tambah kapan saja, lalu Bayar &amp; Tutup Meja saat selesai. Tidak
                                ada pesanan yang terlewat.</p>
                        </div>
                    </article>
                </div>

                <div class="showcase-tie">
                    <div class="tie-txt">
                        <strong>Semua yang Anda lihat di atas ada dalam satu langganan.</strong>
                        <span>Tanpa paket "Pro", tanpa biaya per fitur. Coba gratis {{ $trialDays }} hari, tanpa
                            kartu kredit.</span>
                    </div>
                    <div class="tie-actions">
                        @if ($canRegister)
                            <a href="{{ route('register') }}" class="btn btn-primary btn-lg"><i
                                    data-lucide="store"></i>Coba gratis {{ $trialDays }} hari</a>
                        @endif
                        @if ($plans->isNotEmpty())
                            <a href="#harga" class="btn btn-lg">Lihat harga</a>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== OWNER TENANG ===================== --}}
        <section class="trust-sec" id="pantau">
            <div class="wrap">
                <div class="trust">
                    <div>
                        <span class="eyebrow">Untuk pemilik toko</span>
                        <h2>Titip toko ke kasir, <em>hati tetap tenang</em></h2>
                        <p class="trust-lead">Tidak perlu menunggu di toko seharian. Setiap transaksi masuk ke web
                            admin, jadi Anda bisa cek dari rumah, dari pasar, atau dari luar kota.</p>
                        <ul class="trust-list">
                            <li>
                                <span class="ti"><i data-lucide="receipt-text"></i></span>
                                <span><strong>Setiap transaksi tercatat</strong>
                                    <span class="td">Lengkap dengan jam, isi belanja, dan cara bayarnya, lalu
                                        terkirim ke server.</span></span>
                            </li>
                            <li>
                                <span class="ti"><i data-lucide="chart-line"></i></span>
                                <span><strong>Omzet &amp; stok dari HP atau laptop</strong>
                                    <span class="td">Lihat penjualan hari ini, stok tersisa, dan barang terlaris kapan
                                        saja.</span></span>
                            </li>
                            <li>
                                <span class="ti"><i data-lucide="shield-alert"></i></span>
                                <span><strong>Yang janggal ditandai otomatis</strong>
                                    <span class="td">Transaksi dengan jam HP janggal, stok jadi minus, atau dari HP
                                        yang bukan kasir aktif diberi tanda "cek" di daftar transaksi.</span></span>
                            </li>
                            <li>
                                <span class="ti"><i data-lucide="smartphone"></i></span>
                                <span><strong>Satu HP kasir aktif</strong>
                                    <span class="td">Pindah ke HP lain tercatat di riwayat, jadi Anda tahu kasir
                                        memakai HP yang mana.</span></span>
                            </li>
                        </ul>
                    </div>

                    {{-- contoh layar owner --}}
                    <div class="owner-screen" aria-label="Contoh layar pemilik toko">
                        <div class="os-top">
                            <div>
                                <strong>Transaksi hari ini</strong>
                                <small>Toko Sumber Rejeki · dilihat dari HP owner</small>
                            </div>
                            <span class="os-live"><i></i>Langsung</span>
                        </div>
                        <div class="os-stats">
                            <div class="os-stat"><small>Omzet hari ini</small><b>Rp3.245.000</b></div>
                            <div class="os-stat"><small>Transaksi</small><b>42</b></div>
                        </div>
                        <div class="os-rows">
                            <div class="os-row"><time>14:32</time><span class="who">#0142
                                    <small>· Tunai</small></span><b>87.500</b></div>
                            <div class="os-row"><time>14:18</time><span class="who">#0141
                                    <small>· QRIS</small></span><b>45.000</b></div>
                            <div class="os-row is-flag"><time>13:56</time><span class="who">#0140
                                    <small>· Tunai</small></span><b>120.000</b>
                                <span class="os-flag"><i data-lucide="triangle-alert"></i>Jam HP janggal, perlu
                                    dicek</span>
                            </div>
                            <div class="os-row"><time>13:40</time><span class="who">#0139
                                    <small>· Tunai</small></span><b>32.000</b></div>
                        </div>
                        <p class="os-foot">Data masuk otomatis begitu HP kasir online.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== YANG DIDAPAT ===================== --}}
        <section class="perks-sec">
            <div class="wrap">
                <div class="sec-head">
                    <span class="eyebrow">Satu langganan, semua termasuk</span>
                    <h2>Bayar sekali, toko langsung lengkap</h2>
                    <p>Tidak ada paket "Pro" tersembunyi atau biaya per fitur. Setelah berlangganan, semua ini
                        langsung jadi milik toko Anda.</p>
                </div>
                <div class="perks">
                    <article class="perk is-hero">
                        <span class="perk-icon"><i data-lucide="monitor-smartphone"></i></span>
                        <h3>1 akun untuk Web Admin &amp; Android Kasir</h3>
                        <p>Email dan password yang sama dipakai di laptop untuk mengelola produk dan laporan, dan di HP
                            untuk melayani pembeli. Tidak perlu akun tambahan.</p>
                        <div class="devices" aria-hidden="true">
                            <div class="device">
                                <i data-lucide="laptop"></i>
                                <div><b>Web Admin</b><small>Produk · stok · laporan</small></div>
                            </div>
                            <span class="device-link"><i data-lucide="key-round"></i>login sama</span>
                            <div class="device">
                                <i data-lucide="smartphone"></i>
                                <div><b>Android Kasir</b><small>Jualan · struk · meja</small></div>
                            </div>
                        </div>
                    </article>
                    <article class="perk">
                        <span class="perk-icon"><i data-lucide="sparkles"></i></span>
                        <h3>Semua fitur terbuka</h3>
                        <p>Multi-satuan, stok expire, mode cafe, piutang, supplier, dan laporan laba. Semua ikut di
                            setiap paket.</p>
                    </article>
                    <article class="perk">
                        <span class="perk-icon"><i data-lucide="shield-check"></i></span>
                        <h3>Data aman di server</h3>
                        <p>Transaksi tersimpan di server dan dicadangkan rutin. HP hilang atau rusak, data toko tetap
                            utuh.</p>
                    </article>
                    <article class="perk">
                        <span class="perk-icon"><i data-lucide="chart-line"></i></span>
                        <h3>Pantau toko dari mana saja</h3>
                        <p>Cek omzet, laba, dan stok dari rumah lewat browser, tanpa harus berdiri di depan kasir.</p>
                    </article>
                    <article class="perk">
                        <span class="perk-icon"><i data-lucide="refresh-cw"></i></span>
                        <h3>Update gratis selamanya</h3>
                        <p>Fitur baru dan perbaikan masuk otomatis lewat Play Store, tanpa biaya tambahan.</p>
                    </article>
                    <article class="perk">
                        @if ($wa)
                            <span class="perk-icon"><i data-lucide="message-circle"></i></span>
                            <h3>Dibantu langsung</h3>
                            <p>Ada kendala atau butuh bantuan pindah data? Hubungi admin lewat WhatsApp.</p>
                        @else
                            <span class="perk-icon"><i data-lucide="smartphone"></i></span>
                            <h3>Ganti HP sekali ketuk</h3>
                            <p>HP kasir rusak atau diganti? Pindahkan akses kasir ke HP baru dalam hitungan detik.</p>
                        @endif
                    </article>
                </div>
            </div>
        </section>

        {{-- ===================== MASALAH → SOLUSI ===================== --}}
        <section id="fitur" class="alt">
            <div class="wrap">
                <div class="sec-head">
                    <span class="eyebrow">Fitur</span>
                    <h2>Dibuat untuk repotnya toko &amp; cafe sehari-hari</h2>
                    <p>Bukan sekadar mesin hitung. Aplikasi ini mengurus hal-hal kecil yang biasanya bikin stok
                        selisih dan laba meleset.</p>
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
                        <span class="fix-icon"><i data-lucide="coffee"></i></span>
                        <div>
                            <h3>Cafe &amp; warung makan? Siap.</h3>
                            <p>Simpan pesanan per meja, tambah pesanan kapan saja, cetak checker dapur. Menu racikan
                                seperti es kopi tidak perlu diisi stok.</p>
                        </div>
                        <div class="fix-demo">Meja 3 · 2 Es Kopi Susu<br>+ 1 Nasi Goreng → <b>disimpan</b><br>bayar
                            saat tamu selesai</div>
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
                    <span class="eyebrow">Cara mulai</span>
                    <h2>Mulai jualan hari ini</h2>
                    <p>Daftar dalam 2 menit. Tanpa kartu kredit, tanpa biaya pemasangan.</p>
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
                        <p>Setelah disetujui, uji coba {{ $trialDays }} hari dimulai. Hari menunggu tidak ikut
                            terhitung.</p>
                    </li>
                    <li class="step">
                        <span class="step-no">Langkah 3</span>
                        <h3>Mulai jualan</h3>
                        <p>Masukkan produk (bisa import Excel), sambungkan printer Bluetooth, dan langsung layani
                            pembeli.</p>
                    </li>
                </ol>
            </div>
        </section>

        {{-- ===================== HARGA ===================== --}}
        @if ($plans->isNotEmpty())
            <section id="harga" class="alt">
                <div class="wrap">
                    <div class="sec-head">
                        <span class="eyebrow">Harga</span>
                        <h2>Satu harga, semua fitur</h2>
                        <p>Tanpa biaya pemasangan dan tanpa biaya tambahan per fitur. Pilih yang paling pas dengan cara
                            bayar Anda.</p>
                    </div>
                    <div class="plans">
                        @foreach ($plans as $plan)
                            @php
                                $months = (int) $plan->duration_months;
                                $save = $monthly && $months > 1 ? $monthly->price * $months - $plan->price : 0;
                                $best = $save > 0 && $plan->is($plans->sortByDesc('duration_months')->first());
                                $promo = $bestPromo($plan);
                                $promoPrice = $promo ? $plan->price - $promo->discountFor((int) $plan->price) : null;
                            @endphp
                            <div class="plan {{ $best ? 'is-best' : '' }} {{ $promo ? 'has-promo' : '' }}">
                                <h3>{{ $plan->name }}</h3>
                                @if ($promo)
                                    <div class="price-was">{{ $rp($plan->price) }}</div>
                                    <div class="price">{{ $rp($promoPrice) }}</div>
                                    <div class="per">per {{ $months == 1 ? 'bulan' : $months . ' bulan' }}</div>
                                    <span class="promo-chip"><i data-lucide="ticket-percent"></i>pakai kode
                                        <b>{{ $promo->code }}</b></span>
                                @else
                                    <div class="price">{{ $rp($plan->price) }}</div>
                                    <div class="per">per {{ $months == 1 ? 'bulan' : $months . ' bulan' }}</div>
                                    @if ($save > 0)
                                        <span class="save">Hemat {{ $rp($save) }}</span>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <ul class="included" aria-label="Termasuk di semua paket">
                        <li><i data-lucide="check"></i>Web Admin + Android Kasir</li>
                        <li><i data-lucide="check"></i>Semua fitur</li>
                        <li><i data-lucide="check"></i>Data dicadangkan rutin</li>
                        <li><i data-lucide="check"></i>Update gratis</li>
                    </ul>
                    <p class="plans-note">Bayar lewat transfer bank, lalu kirim bukti dari aplikasi. Masa aktif
                        bertambah setelah pembayaran dikonfirmasi.</p>
                </div>
            </section>
        @endif

        {{-- ===================== TANYA JAWAB ===================== --}}
        <section id="tanya">
            <div class="wrap">
                <div class="sec-head">
                    <span class="eyebrow">Tanya jawab</span>
                    <h2>Yang sering ditanyakan</h2>
                </div>
                <div class="faq">
                    <details>
                        <summary>Apakah 1 akun bisa dipakai di web dan di HP?</summary>
                        <p>Bisa. Setelah berlangganan, email dan password yang sama dipakai untuk Web Admin (kelola
                            produk, stok, dan laporan dari laptop) dan untuk aplikasi Android Kasir di HP.</p>
                    </details>
                    <details>
                        <summary>Saya jarang di toko. Bagaimana memantau kasir?</summary>
                        <p>Buka Web Admin dari HP atau laptop. Setiap transaksi tampil lengkap dengan jam, isi belanja,
                            dan cara bayar, begitu HP kasir tersambung internet. Transaksi yang janggal, misalnya jam HP
                            tidak wajar atau stok jadi minus, diberi tanda "cek" supaya mudah ditemukan.</p>
                    </details>
                    <details>
                        <summary>Cocok untuk cafe atau warung makan?</summary>
                        <p>Cocok. Aktifkan mode cafe untuk menyimpan pesanan per meja dan mencetak checker dapur. Menu
                            racikan seperti nasi goreng atau es kopi bisa dijual tanpa mengisi stok.</p>
                    </details>
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
                        <h2>Mulai jualan lebih tenang, <em>mulai hari ini</em></h2>
                        <p>Layani pembeli walau internet mati, dan cek omzet dari mana saja. Coba gratis
                            {{ $trialDays }} hari — uji coba baru dihitung setelah toko disetujui, jadi tidak ada
                            hari yang terbuang.</p>
                        @if (isset($promos) && $promos->isNotEmpty())
                            @php
                                $firstPromo = $promos->first();
                                $promoEnds = $firstPromo->ends_at
                                    ? ' · berakhir ' . \Illuminate\Support\Carbon::parse($firstPromo->ends_at)->translatedFormat('d M Y')
                                    : '';
                            @endphp
                            <p class="final-promo"><i data-lucide="ticket-percent"></i>Pakai kode
                                <b>{{ $firstPromo->code }}</b> saat berlangganan{{ $promoEnds }}</p>
                        @endif
                    </div>
                    <div class="final-actions">
                        @if ($canRegister)
                            <a href="{{ route('register') }}" class="btn btn-lg"><i
                                    data-lucide="store"></i>Daftarkan toko</a>
                        @endif
                        @if ($playStoreUrl)
                            <a href="{{ $playStoreUrl }}" class="btn btn-lg btn-play" target="_blank"
                                rel="noopener" aria-label="Download di Google Play">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M3.6 2.2C3.4 2.4 3.3 2.8 3.3 3.3v17.4c0 .5.1.9.4 1.1l.1.1 9.7-9.7v-.2L3.6 2.2z"
                                        fill="#00C3FF" />
                                    <path d="M17 15.3l-3.2-3.2v-.2L17 8.7l.1.1 3.9 2.2c1.1.6 1.1 1.7 0 2.3l-4 2z"
                                        fill="#FFCE00" />
                                    <path d="M17.1 15.2L13.8 12 3.6 22.2c.4.4 1 .4 1.7 0l11.8-6.7z" fill="#00E676" />
                                    <path d="M17.1 8.8L5.3 2.1c-.7-.4-1.3-.4-1.7 0L13.8 12l3.3-3.2z" fill="#FF3D47" />
                                </svg>
                                <span class="btn-play-txt"><small>Download di</small><strong>Google Play</strong></span>
                            </a>
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
                <a href="{{ route('privacy') }}">Kebijakan Privasi</a>
                @if ($s->phone || $s->email)
                    &nbsp;|&nbsp;
                @endif
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

        // Hero: slideshow HP Android (otomatis, premium)
        (() => {
            const show = document.getElementById('heroShow');
            if (!show) return;
            const slides = [...show.querySelectorAll('.hp-slide')];
            const dotsWrap = document.getElementById('hpDots');
            const capTxt = document.getElementById('hpCapTxt');
            if (!slides.length) return;

            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            let i = 0;
            let timer = null;

            const dots = slides.map((s, idx) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.setAttribute('aria-label', 'Tampilkan slide ' + (idx + 1));
                b.addEventListener('click', () => {
                    go(idx);
                    restart();
                });
                dotsWrap.appendChild(b);
                return b;
            });

            const go = (n) => {
                i = (n + slides.length) % slides.length;
                slides.forEach((s, idx) => s.classList.toggle('is-active', idx === i));
                dots.forEach((d, idx) => d.classList.toggle('is-active', idx === i));
                if (capTxt) capTxt.textContent = slides[i].dataset.cap || '';
            };

            const start = () => {
                if (!reduce && !timer) timer = setInterval(() => go(i + 1), 3000);
            };
            const stop = () => {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
            };
            const restart = () => {
                stop();
                start();
            };

            go(0);
            start();
            show.addEventListener('mouseenter', stop);
            show.addEventListener('mouseleave', start);
            show.addEventListener('focusin', stop);
            show.addEventListener('focusout', start);
        })();

        // Dua mode jualan → pilihan visual (info usaha)
        (() => {
            const modes = document.querySelectorAll('.mode[data-mode]');
            modes.forEach((btn) => btn.addEventListener('click', () => {
                modes.forEach((b) => {
                    const on = b === btn;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
            }));
        })();
    </script>
    <script>
        // tutup pita promo; diingat per kumpulan kode (promo baru → muncul lagi)
        (function () {
            var bar = document.getElementById('promoBar');
            if (!bar) return;
            var key = 'promoBarClosed:' + (bar.dataset.promo || '');
            try {
                if (localStorage.getItem(key) === '1') { bar.style.display = 'none'; return; }
            } catch (e) {}
            var btn = document.getElementById('promoClose');
            if (btn) btn.addEventListener('click', function () {
                bar.style.display = 'none';
                try { localStorage.setItem(key, '1'); } catch (e) {}
            });
        })();
    </script>
</body>

</html>
