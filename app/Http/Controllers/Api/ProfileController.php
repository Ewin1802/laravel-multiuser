<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * ============================================================
     * UPDATE PROFIL (nama, no HP, foto profil)
     * ============================================================
     *
     * POST /api/profile/update
     *
     * CATATAN BUAT FLUTTER: sama kayak register(), endpoint ini bisa
     * nerima FILE (foto), jadi request WAJIB dikirim sebagai
     * multipart/form-data, bukan JSON body biasa. Field "photo"
     * boleh gak disertakan sama sekali kalau user gak ganti foto.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'phone_number' => [
                'required',
                'string',
                'max:20',
                // ignore user yang sedang login sendiri, biar dia
                // boleh nyimpen ulang nomor HP yang sama persis
                // tanpa kena error "sudah dipakai".
                Rule::unique('users', 'phone_number')->ignore($user->id),
            ],

            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $user->name = $validated['name'];
        $user->phone_number = $validated['phone_number'];

        // ============================================================
        // GANTI FOTO (KALAU ADA FILE BARU DIKIRIM)
        // ============================================================

        if ($request->hasFile('photo')) {

            // Hapus foto lama dulu biar storage gak numpuk file yang
            // udah gak kepake.
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }

            $photo = $request->file('photo');

            $filename = 'user-' . $user->id . '-' . time()
                . '.' . $photo->getClientOriginalExtension();

            $photo->storeAs('users', $filename, 'public');

            $user->photo = 'users/' . $filename;
        }

        $user->save();

        return response()->json([
            'status' => 'success',

            'message' => 'Profil berhasil diperbarui.',

            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $user->role,

                    // photo_url otomatis dari accessor di model User
                    'photo_url' => $user->photo_url,
                ],
            ],
        ], 200);
    }
}
