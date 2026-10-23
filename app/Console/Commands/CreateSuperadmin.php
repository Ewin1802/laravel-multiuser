<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Buat / ganti akun superadmin langsung dari terminal server.
 * Password diketik tersembunyi — tidak pernah ditulis di .env atau seeder.
 *
 *   php artisan superadmin:create
 *
 * Bila superadmin sudah ada, perintah ini mengganti nama / email / HP /
 * password akun tersebut dan mengeluarkan semua sesi aplikasi admin.
 */
class CreateSuperadmin extends Command
{
    protected $signature = 'superadmin:create';

    protected $description = 'Buat atau ganti akun superadmin (email & password baru)';

    public function handle(): int
    {
        $existing = User::where('role', 'superadmin')->orderBy('id')->first();

        if ($existing) {
            $this->warn("Superadmin sudah ada: {$existing->email}");
            if (! $this->confirm('Ganti data akun ini?', true)) {
                return self::SUCCESS;
            }
        }

        $user = $existing ?? new User();

        $name = $this->ask('Nama', $existing->name ?? 'Superadmin');
        $email = strtolower(trim((string) $this->ask('Email', $existing->email ?? null)));
        $phone = trim((string) $this->ask('Nomor HP (unik, mis. 0812...)', $existing->phone_number ?? null));
        $password = (string) $this->secret('Password baru (min. 12 karakter, tidak tampil di layar)');
        $confirm = (string) $this->secret('Ulangi password');

        $validator = Validator::make(
            compact('name', 'email', 'phone', 'password') + ['password_confirmation' => $confirm],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone_number')->ignore($user->id)],
                'password' => ['required', 'string', 'min:12', 'confirmed'],
            ],
            [],
            ['phone' => 'nomor HP']
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // role & store_id sengaja tidak fillable → forceFill
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'phone_number' => $phone,
            'password' => $password, // di-hash otomatis (cast "hashed" di model User)
            'role' => 'superadmin',
            'store_id' => null,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        // sesi aplikasi admin dengan password lama tidak berlaku lagi
        $user->tokens()->delete();

        $this->info(($existing ? 'Superadmin diperbarui: ' : 'Superadmin dibuat: ') . $user->email);
        $this->line('Login di web: ' . rtrim((string) config('app.url'), '/') . '/login');

        return self::SUCCESS;
    }
}
