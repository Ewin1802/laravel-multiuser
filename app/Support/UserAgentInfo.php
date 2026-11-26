<?php

namespace App\Support;

/**
 * Menerjemahkan user-agent menjadi info yang mudah dibaca
 * (sistem operasi + browser) dan mendeteksi bot/skrip.
 *
 * Catatan: token "Safari/537.36" ADA di hampir semua browser berbasis
 * Chromium (Chrome, Vivo, Samsung, Edge, Opera) — itu warisan sejarah,
 * bukan penanda Safari. Browser asli dikenali dari token spesifiknya.
 */
class UserAgentInfo
{
    /** Kata kunci yang menandakan bot/skrip, bukan browser manusia. */
    private const BOT_NEEDLES = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'bingpreview',
        'facebookexternalhit', 'whatsapp', 'telegrambot', 'headless', 'phantomjs', 'selenium',
        'python', 'aiohttp', 'httpx', 'requests', 'urllib', 'http.client',
        'curl', 'wget', 'libwww', 'go-http', 'okhttp', 'axios', 'node-fetch', 'guzzle',
        'java/', 'perl', 'ruby', 'scrapy', 'lighthouse', 'pingdom', 'uptimerobot',
        'semrush', 'ahrefs', 'mj12bot', 'dotbot', 'petalbot', 'censys', 'zgrab',
        'masscan', 'nuclei', 'nmap',
    ];

    public static function isBot(?string $ua): bool
    {
        $u = strtolower(trim((string) $ua));
        if ($u === '') {
            return true;
        }

        foreach (self::BOT_NEEDLES as $n) {
            if (str_contains($u, $n)) {
                return true;
            }
        }

        // Chrome sangat tua (< 90, rilis sebelum 2021) hampir selalu bot menyamar.
        if (preg_match('/chrome\/(\d+)/', $u, $m) && (int) $m[1] < 90) {
            return true;
        }

        return false;
    }

    /** Ringkasan singkat: "Android · Chrome", "Windows · Firefox", "Bot / skrip", dst. */
    public static function summary(?string $ua): string
    {
        $raw = (string) $ua;

        if (self::isBot($raw)) {
            return 'Bot / skrip';
        }

        $os = self::os($raw);
        $browser = self::browser($raw);

        if ($os === '' && $browser === '') {
            return 'Tidak dikenal';
        }
        if ($os === '') {
            return $browser;
        }
        if ($browser === '') {
            return $os;
        }

        return $os . ' · ' . $browser;
    }

    public static function os(string $ua): string
    {
        $u = strtolower($ua);

        return match (true) {
            str_contains($u, 'android') => 'Android',
            str_contains($u, 'iphone') => 'iPhone',
            str_contains($u, 'ipad') => 'iPad',
            str_contains($u, 'windows') => 'Windows',
            str_contains($u, 'cros') => 'ChromeOS',
            str_contains($u, 'mac os'), str_contains($u, 'macintosh') => 'Mac',
            str_contains($u, 'linux') => 'Linux',
            default => '',
        };
    }

    public static function browser(string $ua): string
    {
        // Cek merek spesifik dulu — banyak browser Chromium juga memuat "Chrome/".
        $brands = [
            'VivoBrowser' => 'Vivo Browser',
            'SamsungBrowser' => 'Samsung Internet',
            'MiuiBrowser' => 'Mi Browser',
            'HeyTapBrowser' => 'Oppo Browser',
            'OppoBrowser' => 'Oppo Browser',
            'UCBrowser' => 'UC Browser',
            'OPR/' => 'Opera',
            'Opera' => 'Opera',
            'Edg/' => 'Edge',
            'EdgA/' => 'Edge',
            'YaBrowser' => 'Yandex',
            'Firefox/' => 'Firefox',
            'FxiOS' => 'Firefox',
            'CriOS' => 'Chrome',
        ];

        foreach ($brands as $token => $name) {
            if (stripos($ua, $token) !== false) {
                return $name;
            }
        }

        if (stripos($ua, 'chrome') !== false || stripos($ua, 'chromium') !== false) {
            return 'Chrome';
        }

        // Safari asli: ada "Version/" + "Safari", TANPA "Chrome".
        if (stripos($ua, 'safari') !== false && stripos($ua, 'version/') !== false) {
            return 'Safari';
        }

        return '';
    }
}
