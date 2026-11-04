# SIM Produksi Konveksi — Login Admin

Tahap ini hanya membangun **fungsi login Admin** untuk Sistem Informasi Manajemen Produksi Konveksi.

## Cakupan

- Halaman login React (Vite) yang responsif.
- API login Laravel dengan token Bearer.
- Validasi kredensial dan pembatasan akses hanya untuk role `Admin`.
- Tabel yang digunakan: `users`, `role`, `user_role`.
- Database **SQL Server** melalui `docker-compose.yml`.

## Struktur singkat

```text
Konveksi/
├── backend/                 Laravel API
│   ├── app/Http/Controllers/Api/AuthController.php
│   ├── app/Http/Middleware/AuthenticateApiToken.php
│   ├── app/Support/ApiToken.php
│   ├── database/migrations/2026_11_03_000001_create_role_table.php
│   ├── database/migrations/2026_11_03_000002_create_user_role_table.php
│   └── routes/api.php
├── frontend/                React + Vite
│   └── src/pages/LoginPage.jsx
└── docker-compose.yml       SQL Server + inisialisasi database
```

## Menjalankan

### 1. SQL Server

```bash
docker compose up -d
```

Service `sqlserver-init` akan membuat database `konveksi` secara otomatis setelah SQL Server sehat.

### 2. Backend

```bash
cd backend
composer install
php artisan migrate --seed
php artisan serve
```

Backend berjalan di `http://localhost:8000`.

### 3. Frontend

```bash
cd frontend
npm install
npm run dev
```

Buka `http://localhost:5173`.

Vite meneruskan request `/api` ke backend `http://127.0.0.1:8000` (lihat `frontend/vite.config.js`). Untuk build produksi, atur `VITE_API_BASE_URL` pada `frontend/.env` bila frontend dan backend berada di origin berbeda.

### Akun demo

| Field    | Nilai                 |
| -------- | --------------------- |
| Email    | `admin@konveksi.test` |
| Password | `Admin123!`           |
| Role     | `Admin`               |

## API

### `POST /api/auth/login`

```json
{
  "email": "admin@konveksi.test",
  "password": "Admin123!"
}
```

Respons sukses:

```json
{
  "message": "Login berhasil.",
  "data": {
    "token": "...",
    "token_type": "Bearer",
    "expires_in": 28800,
    "user": {
      "id": 1,
      "name": "Administrator",
      "email": "admin@konveksi.test",
      "roles": [{ "id": 1, "name": "Admin", "slug": "admin" }]
    }
  }
}
```

### `GET /api/auth/me`

Header: `Authorization: Bearer <token>`

## Catatan driver SQL Server

- Konfigurasi utama ada di `backend/.env` (`DB_CONNECTION=sqlsrv`).
- Jika ekstensi PHP `pdo_sqlsrv` tersedia, Laravel memakainya secara otomatis.
- Jika `pdo_sqlsrv` belum terpasang, konfigurasi otomatis memakai `pdo_odbc` + **ODBC Driver 18 for SQL Server**.
- Paksa salah satu driver dengan `DB_ODBC=false` (pdo_sqlsrv) atau `DB_ODBC=true` (ODBC).
- Untuk instalasi standar di Windows, pastikan **ODBC Driver 18 for SQL Server** dan ekstensi `pdo_sqlsrv`/`sqlsrv` sesuai versi PHP terpasang.
