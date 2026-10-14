<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\StoreManager;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Pendaftaran toko dari web. Toko berstatus pending dan akunnya ber-role
 * "user" sampai superadmin menyetujui; trial dimulai saat disetujui.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private StoreManager $stores) {}

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $data = Validator::make($input, [
            'store_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'phone_number' => ['required', 'string', 'max:20', Rule::unique(User::class, 'phone_number')],
            'password' => $this->passwordRules(),
        ])->validate();

        return $this->stores->register($data);
    }
}
