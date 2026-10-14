<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\Setting;
use App\Models\Store;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * STATUS LANGGANAN TOKO — selalu dihitung dengan JAM SERVER.
 *
 *   pending   : baru daftar, belum disetujui superadmin   → tidak bisa transaksi
 *   trial     : dalam 3 hari sejak disetujui              → bisa transaksi
 *   active    : periode berbayar berjalan                 → bisa transaksi
 *   grace     : periode berbayar habis, < 7 hari          → bisa transaksi + peringatan
 *   expired   : trial habis (tanpa tenggang) / tenggang habis → hanya-lihat
 *   suspended : diblokir superadmin                       → hanya-lihat
 *
 * Tenggang 7 hari HANYA untuk toko yang pernah membayar. Trial yang
 * habis langsung hanya-lihat.
 */
class Subscription
{
    public const PENDING = 'pending';
    public const TRIAL = 'trial';
    public const ACTIVE = 'active';
    public const GRACE = 'grace';
    public const EXPIRED = 'expired';
    public const SUSPENDED = 'suspended';

    public const LABELS = [
        self::PENDING => 'Menunggu persetujuan',
        self::TRIAL => 'Masa uji coba',
        self::ACTIVE => 'Aktif',
        self::GRACE => 'Masa tenggang',
        self::EXPIRED => 'Habis (hanya-lihat)',
        self::SUSPENDED => 'Diblokir',
    ];

    private function __construct(
        public readonly Store $store,
        public readonly CarbonImmutable $now,
    ) {}

    public static function for(Store $store, ?CarbonInterface $now = null): self
    {
        return new self($store, CarbonImmutable::instance($now ?? now()));
    }

    public function state(): string
    {
        $s = $this->store;

        if ($s->status === Store::STATUS_SUSPENDED) {
            return self::SUSPENDED;
        }

        if ($s->status !== Store::STATUS_ACTIVE || $s->approved_at === null) {
            return self::PENDING;
        }

        if ($s->paid_until && $this->now->lt($s->paid_until)) {
            return self::ACTIVE;
        }

        if ($s->trial_ends_at && $this->now->lt($s->trial_ends_at)) {
            return self::TRIAL;
        }

        if ($s->paid_until && $this->now->lt($this->graceEndsAt())) {
            return self::GRACE;
        }

        return self::EXPIRED;
    }

    public function label(): string
    {
        return self::LABELS[$this->state()];
    }

    public function canTransact(): bool
    {
        return in_array($this->state(), [self::TRIAL, self::ACTIVE, self::GRACE], true);
    }

    public function hasPaidBefore(): bool
    {
        return $this->store->paid_until !== null;
    }

    public function graceEndsAt(): ?CarbonImmutable
    {
        $paid = $this->store->paid_until;

        return $paid
            ? CarbonImmutable::instance($paid)->addDays((int) ($this->store->grace_days ?: 7))
            : null;
    }

    /**
     * Batas waktu (jam server) transaksi baru masih boleh dibuat.
     * Inilah tanggal yang ditandatangani dan dikirim ke HP.
     */
    public function transactUntil(): ?CarbonImmutable
    {
        if (in_array($this->state(), [self::PENDING, self::SUSPENDED], true)) {
            return null;
        }

        $candidates = array_filter([
            $this->store->trial_ends_at ? CarbonImmutable::instance($this->store->trial_ends_at) : null,
            $this->graceEndsAt(),
        ]);

        if (! $candidates) {
            return null;
        }

        return array_reduce($candidates, fn ($max, $d) => $max === null || $d->gt($max) ? $d : $max);
    }

    /** Akhir masa aktif tanpa tenggang (untuk ditampilkan ke pemilik toko). */
    public function endsAt(): ?CarbonImmutable
    {
        return match ($this->state()) {
            self::TRIAL => CarbonImmutable::instance($this->store->trial_ends_at),
            self::ACTIVE => CarbonImmutable::instance($this->store->paid_until),
            self::GRACE => $this->graceEndsAt(),
            default => null,
        };
    }

    /** Sisa hari (dibulatkan ke atas) sampai endsAt; null bila tidak aktif. */
    public function daysLeft(): ?int
    {
        $end = $this->endsAt();

        if (! $end) {
            return null;
        }

        return max(0, (int) ceil($this->now->diffInSeconds($end, false) / 86400));
    }

    /**
     * Periode yang dibuka oleh pembayaran baru.
     *
     *  - masih trial          → mulai saat trial berakhir (hari trial tidak hangus)
     *  - masih aktif berbayar → menyambung dari paid_until
     *  - masa tenggang        → menyambung dari paid_until (hari tenggang ikut terhitung)
     *  - sudah habis          → mulai sekarang
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function nextPeriod(Plan $plan): array
    {
        $s = $this->store;
        $start = $this->now;

        $paidUntil = $s->paid_until ? CarbonImmutable::instance($s->paid_until) : null;
        $trialEnd = $s->trial_ends_at ? CarbonImmutable::instance($s->trial_ends_at) : null;

        if ($paidUntil && $this->now->lt($this->graceEndsAt())) {
            $start = $paidUntil;                     // aktif atau tenggang
        } elseif ($trialEnd && $trialEnd->gt($this->now)) {
            $start = $trialEnd;                      // masih trial
        }

        return [$start, $start->addMonthsNoOverflow((int) $plan->duration_months)];
    }

    // ------------------------------------------------------------------
    // Aturan platform (bisa diubah superadmin di Pengaturan)
    // ------------------------------------------------------------------

    public static function rules(): object
    {
        static $cache = null;

        if ($cache) {
            return $cache;
        }

        $setting = Setting::query()->first();

        return $cache = (object) [
            'trial_days' => (int) ($setting->trial_days ?? 3) ?: 3,
            'grace_days' => (int) ($setting->grace_days ?? 7) ?: 7,
            'offline_limit_hours' => (int) ($setting->offline_limit_hours ?? 48) ?: 48,
            'offline_warn_hours' => (int) ($setting->offline_warn_hours ?? 36) ?: 36,
        ];
    }
}
