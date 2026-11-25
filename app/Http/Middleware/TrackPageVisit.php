<?php

namespace App\Http\Middleware;

use App\Models\PageVisit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencatat satu kunjungan halaman depan. Dipasang hanya pada route publik
 * (landing & kebijakan privasi). Pencatatan dilakukan SETELAH respons
 * dibuat dan dibungkus try/catch, sehingga kalau DB bermasalah pun halaman
 * tetap tampil normal untuk pengunjung.
 */
class TrackPageVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($request->isMethod('GET') && ! $request->ajax()) {
                $ua = (string) $request->userAgent();

                PageVisit::create([
                    'ip' => $this->clientIp($request),
                    'path' => mb_substr('/' . ltrim($request->path(), '/'), 0, 255),
                    'referer' => ($ref = (string) $request->headers->get('referer')) !== ''
                        ? mb_substr($ref, 0, 255) : null,
                    'user_agent' => $ua !== '' ? mb_substr($ua, 0, 512) : null,
                    'is_bot' => $this->looksLikeBot($ua),
                    'visited_at' => Carbon::now(),
                ]);
            }
        } catch (\Throwable $e) {
            report($e); // jangan pernah ganggu pengunjung
        }

        return $response;
    }

    /**
     * IP pengunjung sebenarnya. Bila aplikasi di belakang reverse proxy
     * (mis. nginx di server yang sama), REMOTE_ADDR bisa loopback —
     * ambil IP pertama dari X-Forwarded-For.
     */
    private function clientIp(Request $request): string
    {
        $ip = (string) $request->ip();
        $fwd = (string) $request->headers->get('x-forwarded-for');

        if ($fwd !== '' && in_array($ip, ['127.0.0.1', '::1', ''], true)) {
            $first = trim(explode(',', $fwd)[0]);
            if ($first !== '') {
                $ip = $first;
            }
        }

        return mb_substr($ip ?: '0.0.0.0', 0, 45);
    }

    private function looksLikeBot(string $ua): bool
    {
        if ($ua === '') {
            return true;
        }

        $needles = [
            'bot', 'crawl', 'spider', 'slurp', 'bingpreview', 'facebookexternalhit',
            'whatsapp', 'telegrambot', 'headless', 'phantom', 'python-requests',
            'curl', 'wget', 'go-http', 'okhttp', 'axios', 'scrapy', 'lighthouse',
            'pingdom', 'uptimerobot', 'semrush', 'ahrefs', 'dotbot',
        ];

        $ua = strtolower($ua);
        foreach ($needles as $n) {
            if (str_contains($ua, $n)) {
                return true;
            }
        }

        return false;
    }
}
