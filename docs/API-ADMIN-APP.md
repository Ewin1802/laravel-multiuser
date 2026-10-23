# API Aplikasi Admin — My Cashier

Aplikasi admin dipakai **superadmin** untuk menyetujui toko baru, menerima notif
"pendaftar baru", dan mengirim pengumuman ke aplikasi kasir.

- Base URL: `https://cashier.zdtech.my.id/api/admin`
- Semua request: header `Accept: application/json`
- Setelah login: header `Authorization: Bearer <token>`
- Format jawaban: `{ "status": "success" | "error", "message"?: "...", "data": ..., "meta"?: ... }`
- Error validasi: HTTP **422** `{ "message": "...", "errors": { "field": ["..."] } }`
- Token kasir **ditolak** di sini (403 `not_admin`), token admin **ditolak** di API kasir.

---

## 1. Login & sesi

### POST `/login`  _(maks. 5x per menit)_
```json
{ "email": "admin@...", "password": "...", "device_name": "Samsung A55" }
```
200:
```json
{ "status": "success", "data": { "token": "12|abc...", "user": { "id": 1, "name": "Ewin", "email": "..." } } }
```
422 bila email/password salah **atau akun bukan superadmin** (pesan sama, sengaja).

### GET `/me`
Profil + angka untuk beranda.
```json
{ "data": {
    "user": { "id": 1, "name": "Ewin", "email": "..." },
    "summary": { "pending_stores": 3, "pending_payments": 1, "stores_total": 42, "kasir_devices": 57 }
} }
```

### POST `/logout`
Menghapus token login **dan** token notifikasi HP ini.

---

## 2. Push notification (FCM)

### POST `/push-token`
Panggil setelah login dan setiap `onTokenRefresh`.
```json
{ "token": "<FCM token>", "platform": "android", "app_version": "1.0.0" }
```

### DELETE `/push-token`
Body opsional `{ "token": "<FCM token>" }`. Tanpa body = token milik sesi login ini.

### Notif yang diterima

| `data.type` | Kapan | Isi `data` |
|---|---|---|
| `new_store` | Ada toko baru mendaftar (web atau aplikasi kasir) | `store_id`, `pending` (jumlah toko menunggu) |
| `test` | `php artisan fcm:test` di server | — |

- Channel Android: **`toko_baru`** — buat di aplikasi admin (lihat bagian 5).
- Saat notif diketuk: buka detail toko `GET /stores/{data.store_id}`.

---

## 3. Toko

### GET `/stores?state=pending&q=&page=1`
`state`: `pending` (default) · `trial` · `active` · `grace` · `expired` · `suspended` · `all`.
Pending diurutkan dari yang **paling lama menunggu**. 20 per halaman.
```json
{ "data": [ {
    "id": 12, "name": "Toko Berkah", "phone": "0812...", "address": "...",
    "status": "pending", "state": "pending", "state_label": "Menunggu persetujuan",
    "is_cafe": false, "suspend_reason": null,
    "registered_at": "2026-10-23T21:05:00+08:00", "approved_at": null,
    "trial_ends_at": null, "paid_until": null,
    "owner": { "name": "Budi", "email": "budi@...", "phone_number": "0812..." }
  } ],
  "meta": { "state": "pending", "page": 1, "per_page": 20, "total": 3, "last_page": 1 } }
```

### GET `/stores/{id}`
Sama seperti di atas, ditambah:
```json
"same_device_stores": [ { "id": 7, "name": "Toko Lama", "state": "expired", "state_label": "Habis (hanya-lihat)", "had_trial": true } ]
```
Isinya toko lain yang memakai **HP yang sama** — tanda kemungkinan daftar ulang untuk trial gratis.
Tampilkan sebagai peringatan sebelum menyetujui.

### POST `/stores/{id}/approve`
Toko aktif, trial dimulai sekarang (hanya sekali per toko). 422 `already_active` bila sudah aktif.

### POST `/stores/{id}/reject`
```json
{ "reason": "Data toko tidak lengkap" }
```
Alasan **wajib**. Pendaftar → ditolak; toko aktif → diblokir (hanya-lihat).

### POST `/stores/{id}/unsuspend`
Buka blokir / batalkan penolakan.

---

## 4. Pengumuman ke aplikasi kasir

### GET `/announcements?page=1`
Riwayat (20 per halaman): `id, title, body, target, store{id,name}|null, status, error, sent_at`.

### POST `/announcements`  _(maks. 10x per menit)_
```json
{ "title": "Pemeliharaan server", "body": "Pukul 23.00–23.30 WITA ...", "target": "all" }
{ "title": "...", "body": "...", "target": "store", "store_id": 12 }
```
- `title` maks. 65 karakter, `body` maks. 240.
- 201 terkirim · 502 gagal kirim (tetap tercatat di riwayat) · 503 `fcm_disabled`.
- Daftar toko untuk pilihan target: `GET /stores?state=all`.

---

## 5. Catatan untuk membuat aplikasi admin (Flutter)

1. **Firebase**: pakai project yang sama dengan aplikasi kasir. Di Firebase Console →
   *Tambahkan aplikasi* → Android, package mis. `id.zdtech.mycashier.admin`. Unduh
   `google-services.json` baru dan taruh di `android/app/` aplikasi admin.
2. `flutter pub add firebase_core firebase_messaging http shared_preferences`
3. Buat channel notifikasi **`toko_baru`** (importance HIGH) — sama seperti
   `MainActivity.kt` aplikasi kasir, ganti ID & nama channel.
4. Di `AndroidManifest.xml`: izin `POST_NOTIFICATIONS` dan meta-data
   `com.google.firebase.messaging.default_notification_channel_id` = `toko_baru`.
5. Alur:
   - login → simpan token → `FirebaseMessaging.instance.requestPermission()` →
     `getToken()` → `POST /push-token`
   - `onTokenRefresh` → `POST /push-token` lagi
   - `onMessageOpenedApp` dan `getInitialMessage()` → bila `data['type'] == 'new_store'`
     buka halaman detail toko `data['store_id']`
   - logout → `POST /logout` → `FirebaseMessaging.instance.deleteToken()`
6. **Jangan** subscribe ke topik `all_users` / `store_*` di aplikasi admin
   (itu topik aplikasi kasir).
7. Simpan token login dengan aman (mis. `flutter_secure_storage`) karena token ini
   bisa menyetujui / memblokir toko.
