# Meowtopia — Backend (Laravel REST API)

Repositori REST API Laravel `/api/v1` untuk **Meowtopia Super App** (dipisahkan dari monorepo `SuperAppMeowtopia`).
Acuan kebutuhan: `docs/PRD.md` (PRD Final v1.1) dan kontrak OpenAPI di `docs/api/`.

## Struktur Branch
- `main`: Branch produksi/rilis utama.
- `development`: Branch integrasi pengembangan (base dari semua branch kelompok).
- Branch kelompok (parent dari `development`):
  - `team/acumalaka` (`app/Modules/FeedingSpot/`, auth & admin)
  - `team/super-sub` (`app/Modules/CatHunter/`)
  - `team/enigma` (`app/Modules/CatAdoption/`)
  - `team/optimind` (`app/Modules/IncidentReport/`)
  - `team/rekans-caps` (`app/Modules/Cat/`, `app/Modules/CatIdentification/`)

## Menjalankan Lokal
```bash
docker compose up -d
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Verifikasi Sebelum PR
```bash
php artisan test
```
