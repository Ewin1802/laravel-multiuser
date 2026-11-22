<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Persetujuan toko otomatis
    |--------------------------------------------------------------------------
    |
    | Bila true, toko yang baru mendaftar langsung disetujui (status aktif)
    | dan masa trial langsung berjalan — pendaftar tidak perlu menunggu
    | superadmin menekan "Setujui". Superadmin tetap menerima notifikasi
    | pendaftar baru dan tetap bisa menangguhkan (suspend) toko kapan saja.
    |
    | Bila false, kembali ke alur lama: toko berstatus "pending" sampai
    | disetujui manual, dan trial baru dihitung setelah disetujui.
    |
    | Atur lewat .env:  STORE_AUTO_APPROVE=true  (atau false)
    |
    */
    'auto_approve_stores' => env('STORE_AUTO_APPROVE', true),

];
