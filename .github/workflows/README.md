# CI/CD

Tiga workflow, mengikuti pola `cafe_order`:

| Workflow | Trigger | Tujuan |
| --- | --- | --- |
| `quality.yml` | push/PR ke `main` | Pint, PHPUnit SQLite, ESLint, build Vite |
| `ci.yml` | push ke `main`, manual | Build image staging, push GHCR, deploy staging |
| `release.yml` | tag `v*.*.*`, branch `prod`, manual | Build image produksi, push GHCR, deploy produksi |

Versi diambil dari `VERSION` di root repo (tag menang untuk release). Image
backend dan frontend dibangun dari `backend/Dockerfile` dan
`frontend/Dockerfile`, lalu diberi tag antara lain `:vX.Y.Z` untuk deploy.

## Secrets yang dibutuhkan

Staging:

- `SSH_HOST`, `SSH_USER`, `SSH_PORT`, `SSH_KEY`
- `GHCR_USERNAME`, `GHCR_TOKEN` (token dengan akses `read:packages` di server)
- `STAGING_BACKEND_ENV` — isi file `.env` backend staging (multiline). Kalau
  kosong, workflow memakai file yang sudah ada di server.

Produksi:

- `SSH_HOST_PROD`, `SSH_USER_PROD`, `SSH_PORT_PROD`, `SSH_KEY_PROD`
- `GHCR_USERNAME`, `GHCR_TOKEN`
- File env produksi harus sudah ada di server (tidak ditulis dari secret).

## Variables opsional

- `VITE_API_BASE_URL` — default `/api/v1`
- `SQLSERVER_CONTAINER` — default `mssql_server`
- `STAGING_BACKEND_HOST_PORT` / `STAGING_FRONTEND_HOST_PORT` — default `8000` / `3080`
- `PROD_BACKEND_HOST_PORT` / `PROD_FRONTEND_HOST_PORT` — default `8000` / `8080`
- `STAGING_BACKEND_ENV_FILE` — default `/opt/konveksi/staging/backend.env`
- `STAGING_PREV_ENV_FILE` — default `/opt/konveksi/staging/previous.env`
- `PROD_BACKEND_ENV_FILE` — default `/opt/konveksi/backend.env`
- `PROD_PREV_ENV_FILE` — default `/opt/konveksi/previous.env`
- `RUN_MIGRATIONS` — default `true`

## Catatan deploy

- Backend berjalan di port `8000` (FrankenPHP). Frontend nginx mem-proxy
  `/api/` ke `BACKEND_UPSTREAM` di Docker network yang sama.
- Container SQL Server (`mssql_server`) otomatis dihubungkan ke network deploy
  bila ada. Isi `DB_HOST=mssql_server` pada env backend.
- Produksi membind backend ke `127.0.0.1`; nginx frontend menjadi satu-satunya
  ingress publik.
- Migrasi dijalankan lewat `docker exec <backend> php artisan migrate --force`.
- Jika container gagal running setelah deploy, workflow mencoba rollback ke
  image sebelumnya yang disimpan di `previous.env`.
