<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak — Toko Utama Caterpillar</title>

    {{-- Favicon --}}
    <link rel="icon" href="{{ asset('img/logo_arch_web.png') }}" type="image/png">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap"
        rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
            font-family: 'Poppins', sans-serif;
            background: radial-gradient(circle at 20% 20%, #4a2c1c 0%, transparent 45%),
                radial-gradient(circle at 85% 80%, #5a3620 0%, transparent 45%),
                linear-gradient(180deg, #2f1b12 0%, #43291d 48%, #1f120c 100%);
            position: relative;
            overflow: hidden;
        }

        /* Decorative floating bean shapes */
        body::before,
        body::after {
            content: "";
            position: absolute;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(229, 185, 145, .10), transparent 70%);
            z-index: 0;
        }

        body::before {
            top: -80px;
            left: -80px;
        }

        body::after {
            bottom: -100px;
            right: -100px;
        }

        .container {
            position: relative;
            z-index: 1;
            text-align: center;
            background: linear-gradient(180deg, rgba(255, 250, 245, .06), rgba(255, 250, 245, .02));
            border: 1px solid rgba(255, 250, 245, .12);
            backdrop-filter: blur(6px);
            padding: 56px 44px;
            border-radius: 30px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, .35);
            max-width: 480px;
            width: 100%;
        }

        .icon-badge {
            width: 78px;
            height: 78px;
            margin: 0 auto 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg, #e5b991, #b9784d);
            box-shadow: 0 12px 25px rgba(185, 120, 77, .35);
        }

        .icon-badge svg {
            width: 34px;
            height: 34px;
            stroke: #2f1b12;
        }

        .eyebrow {
            color: rgba(255, 250, 245, .55);
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            margin: 0 0 6px;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 96px;
            line-height: 1;
            margin: 0 0 18px;
            font-weight: 700;
            letter-spacing: -2px;
            background: linear-gradient(180deg, #f3dcc2, #b9784d);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .divider {
            width: 56px;
            height: 3px;
            margin: 0 auto 24px;
            border-radius: 999px;
            background: linear-gradient(90deg, #e5b991, #b9784d);
        }

        .message-title {
            color: #fffaf5;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 10px;
            font-family: 'Playfair Display', serif;
        }

        .message-text {
            color: rgba(255, 250, 245, .65);
            font-size: 15px;
            line-height: 1.7;
            margin: 0 0 34px;
        }

        .actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        a.btn-home,
        button.btn-logout {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 26px;
            text-decoration: none;
            border-radius: 14px;
            font-weight: 600;
            font-size: 14.5px;
            font-family: 'Poppins', sans-serif;
            border: none;
            cursor: pointer;
            transition: .25s ease;
        }

        a.btn-home {
            background: #fffaf5;
            color: #2f1b12;
            box-shadow: 0 12px 25px rgba(0, 0, 0, .25);
        }

        a.btn-home:hover {
            background: #e5b991;
            transform: translateY(-2px);
            box-shadow: 0 16px 30px rgba(0, 0, 0, .3);
        }

        button.btn-logout {
            background: rgba(255, 250, 245, .06);
            color: rgba(255, 250, 245, .85);
            border: 1px solid rgba(255, 250, 245, .22);
        }

        button.btn-logout:hover {
            background: rgba(169, 85, 69, .16);
            border-color: rgba(233, 170, 160, .4);
            color: #e9aaa0;
            transform: translateY(-2px);
        }

        a.btn-home svg,
        button.btn-logout svg {
            width: 18px;
            height: 18px;
        }

        .footnote {
            margin-top: 28px;
            color: rgba(255, 250, 245, .35);
            font-size: 12.5px;
            letter-spacing: .5px;
        }

        @media (max-width: 480px) {
            .container {
                padding: 40px 26px;
            }

            h1 {
                font-size: 72px;
            }
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="icon-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="10" rx="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>

        <p class="eyebrow">Toko Utama Caterpillar · Point Of Sales</p>

        <h1>403</h1>

        <div class="divider"></div>

        <p class="message-title">Ini Adalah Daerah Terlarang</p>

        <p class="message-text">
            Maaf, Anda tidak memiliki akses untuk membuka halaman ini.
            Silakan kembali ke halaman sebelumnya atau hubungi administrator
            jika Anda merasa ini sebuah kesalahan.
        </p>

        <div class="actions">

            <a href="{{ url()->previous() }}" class="btn-home">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M19 12H5"></path>
                    <path d="M12 19l-7-7 7-7"></path>
                </svg>
                Kembali
            </a>

            @auth
                <form action="{{ route('logout') }}" method="POST">
                    @csrf

                    <button type="submit" class="btn-logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        Logout
                    </button>
                </form>
            @endauth

        </div>

        @auth
            <p class="footnote">
                Masuk sebagai <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->role }})
            </p>
        @else
            <p class="footnote">Terima kasih atas pengertiannya.</p>
        @endauth

    </div>

</body>

</html>
