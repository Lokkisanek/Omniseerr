# OMNISEERR — Master Technical Specification & Architecture

## 1. Project Overview
Omniseerr is a self-hosted, single-container all-in-one media request, interactive release selection, and live download management platform. It bridges the gap between Overseerr (discovery UI), Radarr/Sonarr (interactive indexer search & custom format scoring), Prowlarr, and qBittorrent (real-time transfer telemetry).

## 2. Core Philosophy & "Easy Install" Architecture
- **Zero-Config Environment:** Users must NOT be forced to fill out 25 `.env` variables before booting. The container boots immediately with an embedded **SQLite** database (`database/omniseerr.sqlite` mounted to `/config`).
- **First-Run Onboarding Wizard:** On first visit (`/`), if services are not configured, a sleek 4-step Setup Wizard guides the user through entering URLs & API keys for TMDB, Radarr, Sonarr, and qBittorrent, including real-time "Test Connection" buttons.
- **Single Docker Image:** Multi-stage Docker build packaging PHP 8.3 (FPM), Nginx, Supervisor, Laravel Queue Worker, Laravel Reverb (WebSockets), and pre-built Vite/Vue 3 assets on Alpine Linux.

## 3. Tech Stack
- **Backend:** Laravel 11 (PHP 8.3), SQLite (WAL mode enabled for high concurrency), Laravel Reverb (WebSockets).
- **Frontend:** Vue 3 (Composition API, `<script setup>`), Inertia.js, Tailwind CSS, Lucide Icons, Pinia (for global active downloads bar).
- **Design System:** Dark-mode first (`#090D16` background), glassmorphism overlays (`backdrop-blur-xl bg-slate-900/75`), iOS/macOS inspired sleek borders (`border-white/10`), vibrant status badges.

## 4. End-to-End User Workflows

### Workflow A: Discovery & Unified Status
1. User searches or browses Trending/Popular Movies & TV via TMDB API.
2. Backend cross-references returned `tmdbId` array against local cached Radarr/Sonarr library states:
   - `AVAILABLE` (Green badge — already downloaded & imported).
   - `DOWNLOADING` (Animated Blue badge with live % — currently in qBittorrent/Arr queue).
   - `MONITORED` (Amber badge — in Radarr/Sonarr, waiting for release).
   - `UNREQUESTED` (No badge — ready to grab).

### Workflow B: The "Ghost-Add" Interactive Grab (Core Killer Feature)
Radarr v3 and Sonarr v3/v4 require a Movie/Series to exist in their local database before triggering an Interactive Release Search (`/api/v3/release?movieId=X`), UNLESS queried carefully or temporarily registered. To ensure 100% compatibility across all Custom Formats, Quality Profiles, and Rejections:
1. User opens a Movie modal in Omniseerr and clicks **"Interactive Grab"**.
2. User selects desired `Quality Profile` and `Root Folder` (pre-filled with user's defaults).
3. **Check & Ghost-Add:**
   - Omniseerr checks if `tmdbId` exists in Radarr (`GET /api/v3/movie?tmdbId=X`).
   - If NOT in Radarr: Omniseerr adds the movie via `POST /api/v3/movie` with `monitored: false` and `addOptions.searchForMovie: false` (prevents auto-downloading random releases) and flags it in Omniseerr SQLite DB as `is_ghost = true`.
4. **Live Release Query:**
   - Omniseerr calls `GET /api/v3/release?movieId={radarrId}` (timeout set to 60s as Prowlarr queries all indexers).
   - Omniseerr transforms and sorts the response: displaying Release Title, Indexer, Size (GB), Seeders/Leechers, Quality Badge (2160p, BluRay, WEB-DL), Audio/HDR tags, Custom Format Score (`+150`), and Rejection Reasons (if any).
5. **Direct Grab Execution:**
   - User clicks **"Download"** on a specific torrent row.
   - Omniseerr sends `POST /api/v3/release` with `{ "guid": release.guid, "indexerId": release.indexerId }`.
   - Omniseerr updates the movie in Radarr to `monitored: true` and removes the `is_ghost` flag.
6. **Ghost Cleanup (Abandonment):**
   - If the user closes the modal without picking a release (or after a 15-minute background cleanup job), any movie still marked `is_ghost = true` with zero downloads is deleted from Radarr (`DELETE /api/v3/movie/{id}`) so the user's Radarr library stays 100% clean.

### Workflow C: Real-Time Telemetry & Control Center
1. A background daemon runs every 2 seconds when clients are connected:
   - Fetches `GET /api/v3/queue` from Radarr & Sonarr (maps `downloadId` [infohash] -> TMDB metadata, poster, title, import status).
   - Fetches `GET /api/v2/torrents/info` from qBittorrent (maps `hash` -> exact download speed `dlspeed`, `upspeed`, `eta`, `progress`, `num_seeds`, `state`).
2. Merges both payloads by matching `UPPER(infohash)` so the user sees:
   - Movie Poster & Clean Title (instead of ugly release file names) + Release Group badge + Live MB/s speed + Progress bar + Pause/Resume/Delete/Force-Recheck buttons directly in Omniseerr.