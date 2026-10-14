<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
// use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


// role & store_id SENGAJA tidak fillable: hanya diubah lewat kode server
// (pendaftaran toko / persetujuan superadmin), tidak pernah dari input form.
#[Fillable(['name', 'email', 'phone_number', 'date_of_birth', 'photo', 'password'])]
#[Hidden([
    'password',
    'remember_token',
    // Field 2FA Fortify ini KETAHUAN KEBOCORAN pas ngecek log —
    // endpoint /api/user (yang balikin $request->user() mentah)
    // ternyata ikut ngirim ini ke client. Ditutup di sini biar
    // gak ke-expose lagi di response manapun.
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
     use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
        ];
    }

    // ============================================================
    // PHOTO URL (ACCESSOR)
    // ============================================================
    //
    // Sengaja dibikin accessor di sini (bukan diitung manual di tiap
    // controller kayak sebelumnya), supaya photo_url OTOMATIS muncul
    // di SEMUA response yang ngeluarin data User — register, login,
    // /api/user, dan endpoint apapun di masa depan. Sebelumnya
    // endpoint /api/user gak lewat logic manual di AuthController,
    // jadi cuma balikin 'photo' (path mentah), bukan 'photo_url'
    // (URL lengkap) — itu penyebab foto ilang pas Home refresh
    // manggil /api/user.
    //
    // $appends WAJIB ada, karena accessor gak otomatis kebawa pas
    // model di-convert ke array/JSON kecuali di-appends manual.
    //
    // ============================================================

    protected $appends = ['photo_url'];

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->photo
                ? asset('storage/' . $this->photo)
                : null,
        );
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isStoreAdmin(): bool
    {
        return $this->role === 'admin' && $this->store_id !== null;
    }
}
