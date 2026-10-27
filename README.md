# Omniseerr

Self-hosted media request desk with interactive Radarr release selection and live qBittorrent telemetry.

## Stack

- Laravel 13 (PHP 8.3+), SQLite (WAL), Laravel Reverb
- Vue 3 + Inertia.js + Tailwind CSS + Pinia
- Single Docker image: Nginx + PHP-FPM + Supervisor

## Quick start (Docker)

```bash
docker compose up --build -d
```

Open [http://localhost:5056](http://localhost:5056) and complete the setup wizard (TMDB → Radarr → Sonarr optional → qBittorrent).

Persistent data lives in the `omniseerr-config` volume (`/config/omniseerr.sqlite` + encrypted `APP_KEY`).

## Local development

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run dev
# other terminal
php artisan serve
php artisan reverb:start
php artisan omniseerr:poll-telemetry
```

## Core flows

1. **Setup wizard** — stores encrypted API credentials in SQLite (no `.env` service keys).
2. **Discover** — TMDB catalog with library badges (`AVAILABLE` / `DOWNLOADING` / `MONITORED` / `UNREQUESTED`).
3. **Interactive Grab** — Ghost-Add into Radarr, Interactive Search via Prowlarr indexers, manual release pick.
4. **Activity dock** — merges Arr queue + qBittorrent torrent stats every 2s while clients are connected.

See `SPECIFICATION.md` for the full architecture.
