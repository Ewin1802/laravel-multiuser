<?php

namespace App\Console\Commands;

use App\Models\PageVisit;
use App\Support\UserAgentInfo;
use Illuminate\Console\Command;

/**
 * Menandai ulang baris page_visits sebagai bot/manusia berdasarkan
 * user-agent terbaru. Berguna setelah aturan deteksi bot diperbarui,
 * agar data lama (mis. aiohttp/Chrome lawas) ikut difilter.
 */
class ReflagVisitBots extends Command
{
    protected $signature = 'visits:reflag-bots {--dry : Hanya menghitung, tanpa mengubah data}';

    protected $description = 'Tandai ulang kunjungan sebagai bot/manusia dari user-agent';

    public function handle(): int
    {
        $toBot = [];
        $toHuman = [];
        $scanned = 0;

        PageVisit::query()
            ->select('id', 'user_agent', 'is_bot')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$toBot, &$toHuman, &$scanned) {
                foreach ($rows as $r) {
                    $scanned++;
                    $bot = UserAgentInfo::isBot($r->user_agent);
                    if ($bot === (bool) $r->is_bot) {
                        continue;
                    }
                    if ($bot) {
                        $toBot[] = $r->id;
                    } else {
                        $toHuman[] = $r->id;
                    }
                }
            });

        $this->info("Diperiksa: {$scanned} baris.");
        $this->line('  → jadi bot    : ' . count($toBot));
        $this->line('  → jadi manusia: ' . count($toHuman));

        if ($this->option('dry')) {
            $this->comment('Mode --dry: tidak ada perubahan disimpan.');

            return self::SUCCESS;
        }

        foreach (array_chunk($toBot, 500) as $ids) {
            PageVisit::whereIn('id', $ids)->update(['is_bot' => true]);
        }
        foreach (array_chunk($toHuman, 500) as $ids) {
            PageVisit::whereIn('id', $ids)->update(['is_bot' => false]);
        }

        $this->info('Selesai. ' . (count($toBot) + count($toHuman)) . ' baris diperbarui.');

        return self::SUCCESS;
    }
}
