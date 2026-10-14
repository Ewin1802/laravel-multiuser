<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        // Informasi Toko
        'store_name',
        'store_tagline',
        'store_description',

        // Logo
        'logo',
        'favicon',

        // Hero Landing Page
        'hero_title',
        'hero_subtitle',
        'hero_button',

        // Kontak
        'phone',
        'whatsapp',
        'email',
        'address',
        'google_maps',

        // Social Media
        'facebook',
        'instagram',
        'youtube',
        'tiktok',

        // SEO
        'meta_title',
        'meta_description',
        'meta_keywords',

        // Footer
        'copyright',
        'server_provider',
        'server_due_date',
        'server_billing_months',
        'server_cost',
        'server_remind_days',

        // Status Website
        'maintenance_mode',

        // Fitur aplikasi kasir (dicentang dari web admin)
        'trial_days',
        'grace_days',
        'offline_limit_hours',
        'offline_warn_hours',
        'payment_instructions',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
    ];

    // =====================================================
    // ACCESSORS
    // =====================================================

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->favicon ? asset('storage/' . $this->favicon) : null;
    }
}
