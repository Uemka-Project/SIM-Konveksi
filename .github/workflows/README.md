# CI/CD

Dua workflow:

| Workflow | Trigger | Tujuan |
| --- | --- | --- |
| `quality.yml` | push/PR ke `main` | Pint, PHPUnit SQLite, ESLint, build Vite |
| `release.yml` | push ke `main`, tag `v*.*.*`, manual | Build image produksi, push GHCR, deploy produksi |

Tidak ada environment staging. Setiap push ke `main` langsung men-deploy ke
produksi; tag `v*.*.*` dipakai untuk rilis berversi. Versi diambil dari
`VERSION` di root repo (tag menang). Image backend dan frontend dibangun dari
`backend/Dockerfile` dan `frontend/Dockerfile`, lalu diberi tag `:prod`,
`:latest`, `:vX.Y.Z`, dan `:sha-xxxxxxx`.

## Secrets yang dibutuhkan

- `SSH_HOST_PROD`, `SSH_USER_PROD`, `SSH_PORT_PROD`, `SSH_KEY_PROD`
- `GHCR_USERNAME`, `GHCR_TOKEN` (token dengan akses `read:packages` di server)
- File env produksi harus sudah ada di server (tidak ditulis dari secret).

## Variables opsional

- `VITE_API_BASE_URL` — default `/api/v1`
- `SQLSERVER_CONTAINER` — default `mssql_server`
- `PROD_BACKEND_HOST_PORT` / `PROD_FRONTEND_HOST_PORT` — default `8000` / `8080`
- `PROD_BACKEND_ENV_FILE` — default `/opt/konveksi/backend.env`
- `PROD_PREV_ENV_FILE` — default `/opt/konveksi/previous.env`
- `RUN_MIGRATIONS` — default `true`

## Catatan deploy

- Backend berjalan di port `8000` (FrankenPHP). Frontend nginx mem-proxy
  `/api/` ke `BACKEND_UPSTREAM` di Docker network yang sama.
- Container SQL Server (`mssql_server`) otomatis dihubungkan ke network deploy
  bila ada. Isi `DB_HOST=mssql_server` pada env backend.
- Backend hanya bind ke `127.0.0.1`; nginx frontend menjadi satu-satunya
  ingress publik.
- Migrasi dijalankan lewat `docker exec <backend> php artisan migrate --force`.
- Jika container gagal running setelah deploy, workflow mencoba rollback ke
  image sebelumnya yang disimpan di `previous.env`.
