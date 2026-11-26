<?php

namespace App\Modules\CatHunter\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data contoh CatHunter: 4 zona kampus + kucing contoh (oren/putih/hitam) dengan quote & token QR.
 * Idempotent: aman dijalankan berulang dan tidak menimpa nama kucing yang sudah diganti pemain.
 */
class CatHunterSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Empat zona wilayah kampus (tabel territories dipakai bersama; kode yang sudah ada dipertahankan).
        $zones = [
            'TULT_ASRAMA' => 'TULT & Asrama',
            'GKU' => 'GKU',
            'CACUK' => 'Cacuk',
            'FAKULTAS_DEPAN' => 'Fakultas Depan',
        ];

        $territoryIds = [];
        foreach ($zones as $code => $name) {
            $existing = DB::table('territories')->where('code', $code)->first();
            if ($existing) {
                $territoryIds[$code] = $existing->id;
                continue;
            }

            $territoryIds[$code] = (string) Str::ulid();
            DB::table('territories')->insert([
                'id' => $territoryIds[$code],
                'code' => $code,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Kucing contoh (selaras dengan data mock frontend telcat world).
        $cats = [
            ['TELCAT-TULT-001', 'Si Oyen', 'Oren', 'MALE', 'TULT_ASRAMA', 'Lobby Utama Gedung TULT Lt. 1', 'Pemberani & Suka Camilan', true, 'Kalau bawa ikan tongkol, kita langsung bestie!'],
            ['TELCAT-ASRAMA-002', 'Kucing Putih #02', 'Putih', 'FEMALE', 'TULT_ASRAMA', 'Teras Gazebo Asrama Putri', 'Manja & Suka Mendengkur', true, 'Kardus hangat ini adalah singgasana terbaikku!'],
            ['TELCAT-CACUK-003', 'Kuro Danau', 'Hitam', 'MALE', 'CACUK', 'Tepian Jalur Jogging Dekat Gedung Cacuk', 'Misterius & Mandiri', true, 'Jangan kaget kalau aku tiba-tiba muncul di pinggir danau ya!'],
            ['TELCAT-GKU-004', 'Kucing Oren #04', 'Oren', 'FEMALE', 'GKU', 'Koridor Depan Gedung Kuliah Bersama Lt. 2', 'Waspada tapi Penasaran', false, 'Ssst... aku sedang menyimak kuliah umum dari balik pilar koridor!'],
            ['TELCAT-GKU-005', 'Kucing Putih #05', 'Putih', 'MALE', 'GKU', 'Teras Teduh Sisi Timur Gedung GKU', 'Santai & Hobi Tidur', true, 'Skripsi boleh menumpuk, tapi tidur siang tetap nomor satu.'],
            ['TELCAT-FAKDEP-006', 'Luna Fakultas', 'Putih', 'FEMALE', 'FAKULTAS_DEPAN', 'Bawah Pohon Rindang Kompleks Fakultas Depan', 'Ramah & Suka Dibelai', true, 'Elus kepalaku sebentar sebelum masuk kelas ya, biar harimu cerah!'],
            ['TELCAT-CACUK-007', 'Kucing Hitam #07', 'Hitam', 'MALE', 'FAKULTAS_DEPAN', 'Pojok Parkir Motor Fakultas Depan', 'Pendiam & Pengamat', false, 'Aku jagain motor kamu, bayarannya cukup satu elusan.'],
        ];

        foreach ($cats as [$code, $name, $color, $gender, $zone, $location, $temperament, $sterilized, $quote]) {
            $cat = DB::table('cats')->where('unique_code', $code)->first();
            $catId = $cat->id ?? (string) Str::ulid();

            if (!$cat) {
                DB::table('cats')->insert([
                    'id' => $catId,
                    'unique_code' => $code,
                    'name' => $name,
                    'is_name_locked' => false,
                    'gender' => $gender,
                    'color_pattern' => $color,
                    'primary_color' => $color,
                    'age_group' => 'ADULT',
                    'is_sterilized' => $sterilized,
                    'description' => $location,
                    'quote' => $quote,
                    'temperament' => $temperament,
                    'territory_id' => $territoryIds[$zone],
                    'presence_status' => 'ON_CAMPUS',
                    'health_status' => 'HEALTHY',
                    'adoption_status' => 'UNREVIEWED',
                    'verification_status' => 'VERIFIED',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Token QR deterministik agar mudah dipakai untuk pengujian: MTP1.C.<token>
            if (!DB::table('cat_qr_tokens')->where('cat_id', $catId)->where('status', 'ACTIVE')->exists()) {
                DB::table('cat_qr_tokens')->insert([
                    'id' => (string) Str::ulid(),
                    'cat_id' => $catId,
                    'token' => substr(hash('sha256', 'catHunter-qr-' . $code), 0, 32),
                    'status' => 'ACTIVE',
                    'issued_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
