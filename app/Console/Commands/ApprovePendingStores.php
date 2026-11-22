<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\StoreManager;
use Illuminate\Console\Command;

/**
 * Menyetujui semua toko yang masih "pending" sekaligus. Berguna untuk
 * membersihkan antrean pendaftar lama saat mengaktifkan persetujuan
 * otomatis (STORE_AUTO_APPROVE=true). Trial tiap toko mulai dihitung
 * sejak disetujui dan hanya sekali per toko.
 */
class ApprovePendingStores extends Command
{
    protected $signature = 'stores:approve-pending {--dry : Hanya menampilkan daftar, tanpa menyetujui}';

    protected $description = 'Setujui semua toko yang masih menunggu persetujuan';

    public function handle(StoreManager $stores): int
    {
        $pending = Store::where('status', Store::STATUS_PENDING)->orderBy('id')->get();

        if ($pending->isEmpty()) {
            $this->info('Tidak ada toko yang menunggu persetujuan.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nama Toko', 'Terdaftar'],
            $pending->map(fn (Store $s) => [
                $s->id,
                $s->name,
                optional($s->created_at)->format('d/m/Y H:i'),
            ])->all()
        );

        if ($this->option('dry')) {
            $this->comment("Mode --dry: {$pending->count()} toko TIDAK disetujui.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Setujui {$pending->count()} toko di atas sekarang?", true)) {
            $this->comment('Dibatalkan.');

            return self::SUCCESS;
        }

        foreach ($pending as $store) {
            $stores->approve($store); // sistem (approved_by = null)
            $this->line("  ✓ Disetujui: #{$store->id} {$store->name}");
        }

        $this->info("Selesai. {$pending->count()} toko disetujui dan trial-nya berjalan.");

        return self::SUCCESS;
    }
}
