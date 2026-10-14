<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Akun superadmin (pemilik aplikasi). Atur lewat .env sebelum seeding:
 *   SUPERADMIN_EMAIL, SUPERADMIN_PHONE, SUPERADMIN_PASSWORD
 * Bila password kosong, dibuatkan acak dan ditampilkan sekali di terminal.
 */
class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'superadmin@example.com');
        $password = env('SUPERADMIN_PASSWORD') ?: Str::password(16);

        $user = User::firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;

        $user->fill([
            'name' => env('SUPERADMIN_NAME', 'Superadmin'),
            'phone_number' => env('SUPERADMIN_PHONE', '080000000000'),
        ]);
        if ($isNew || env('SUPERADMIN_PASSWORD')) {
            $user->password = Hash::make($password);
        }
        $user->role = 'superadmin';
        $user->store_id = null;
        $user->save();

        if ($isNew && ! env('SUPERADMIN_PASSWORD')) {
            $this->command?->warn("Superadmin dibuat: {$email} / {$password}  (simpan, lalu ganti)");
        }
    }
}
