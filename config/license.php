<?php

/*
|--------------------------------------------------------------------------
| LISENSI KASIR (token bertanda tangan Ed25519)
|--------------------------------------------------------------------------
|
| Server menandatangani status langganan + jam server dengan KUNCI PRIVAT.
| Aplikasi kasir hanya menyimpan KUNCI PUBLIK, jadi isi token tidak bisa
| dipalsukan di HP (mis. mengubah tanggal berakhir).
|
| Buat pasangan kunci sekali saja:
|     php artisan license:keygen
| lalu salin hasilnya ke .env. Kunci publik juga ditempel di aplikasi
| Flutter (lib/core/license/license_keys.dart).
|
| JANGAN mengganti kunci setelah aplikasi beredar — semua HP akan
| menolak token baru sampai aplikasinya diperbarui.
|
*/

return [
    'private_key' => env('LICENSE_PRIVATE_KEY'), // base64, 64 byte
    'public_key' => env('LICENSE_PUBLIC_KEY'),   // base64, 32 byte
];
