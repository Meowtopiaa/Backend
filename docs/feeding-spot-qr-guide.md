# Panduan Generate QR Spot (`MTP1.S`) & Uji Scan Ujung ke Ujung (MEOW-28)

Dokumen ini menjelaskan cara membuat QR Code spot resmi Meowtopia di backend dan mengujinya secara end-to-end dengan aplikasi Flutter (Android/iOS).

## 1. Format Resmi QR Meowtopia (PRD §2 & §4.2)
- **QR Spot Pakan:** `MTP1.S.<token>` (token acak 32 karakter)
- **Kode Manual Cadangan Spot:** `code` spot (contoh: `SPOT-TULT-01`, `SPOT-GKU-01`, `SPOT-ASRP-01`)
- **QR Kalung Kucing:** `MTP1.C.<token>` (harus ditolak di alur Feeding Spot dengan kode `QR_WRONG_TYPE`)

## 2. Siapkan Data Spot Contoh di Backend
Pastikan database lokal sudah termigrasi dan terisi seeder:
```bash
php artisan migrate --seed
# atau gunakan command setup lokal feeding:
php artisan feeding:setup-local
```

## 3. Generate Berkas QR Spot Contoh (SVG & PNG)
Jalankan perintah Artisan:
```bash
# Generate SVG dan PNG untuk semua spot di database
php artisan feeding:sample-qr

# Generate hanya untuk satu spot tertentu
php artisan feeding:sample-qr --spot=SPOT-TULT-01 --format=png
```
Hasil berkas tersimpan di `storage/app/qr-spots/<KODE_SPOT>.svg` dan `storage/app/qr-spots/<KODE_SPOT>.png`, serta tabel payload `MTP1.S.<token>` dicetak langsung di terminal.

### Alternatif via Panel Admin Web / API
- **Cetak semua QR spot aktif:** `GET /admin/feeding/qr-sheet` (login admin `spot.manage`) atau `GET /api/v1/admin/feeding/spots/qr-sheet`
- **Cetak QR satu spot:** `GET /admin/feeding/spots/{id}/qr-sheet`
- **Unduh QR PNG/SVG:** `GET /admin/feeding/spots/{id}/qr-download?format=png`

## 4. Checklist 4 Skenario Uji Scan Ujung ke Ujung (IT-02, IT-03, MEOW-28)

| # | Skenario | Langkah Uji | Hasil yang Diharapkan |
| :-: | :--- | :--- | :--- |
| 1 | **QR Spot Valid & Aktif** | Tampilkan/cetak QR `MTP1.S.<token>` milik spot `ACTIVE`, pindai dengan aplikasi Flutter (atau kirim ke `GET /api/v1/scan/resolve` dan `POST /api/v1/feeding/activities` dengan `Idempotency-Key`). | `GET /scan/resolve` mengembalikan `type: spot`; `POST /feeding/activities` membuat sesi `DRAFT` (HTTP 201) dengan deadline 15 menit & 60 menit. |
| 2 | **Kode Manual Spot** | Masukkan kode manual (mis. `SPOT-TULT-01`) pada input manual scanner. | Terbaca sebagai spot valid dan membuka sesi `DRAFT` yang sama. |
| 3 | **QR Kucing di Alur Pakan (IT-03)** | Pindai QR kalung kucing (`MTP1.C.<token>`) di halaman scan spot pakan. | Ditolak dengan HTTP `422` kode **`QR_WRONG_TYPE`**; tidak ada sesi `DRAFT` yang dibuat. |
| 4 | **QR Spot Dicabut / Diganti (IT-02)** | Lakukan *Ganti QR* pada spot (`POST /api/v1/admin/feeding/spots/{id}/qr/regenerate`), lalu pindai QR lama. | Ditolak dengan kode **`QR_REVOKED`** (baik di `GET /api/v1/scan/resolve` maupun `POST /api/v1/feeding/activities`), sedangkan QR baru dan kode manual spot tetap berfungsi. |

## 5. Verifikasi Uji Otomatis
```bash
# Backend (termasuk uji QR_REVOKED, QR_WRONG_TYPE, ScanResolver, dan sample-qr)
php artisan test --filter=FeedingSpot

# Frontend (unit test parseQr di repo Frontend)
flutter test test/feeding_spot_test.dart
```
