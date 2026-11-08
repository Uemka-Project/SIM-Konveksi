# SIM Produksi Konveksi Sejahtera

Sistem informasi manajemen produksi konveksi (make-to-order) berbasis Laravel API +
React SPA. Mencakup pesanan, pembayaran, kebutuhan bahan (BOM), pembelian, stok,
progres produksi, serah produk, dashboard, laporan, dan dokumen FOD dalam PDF.

## Cakupan fitur

- **Autentikasi & akses**: login username/email dengan token Sanctum, logout, cek sesi,
  kelola pengguna dan peran (owner), serta matriks hak akses per modul.
- **Data master**: pelanggan, pemasok, karyawan, bahan baku, produk, varian, dan BOM.
- **Pesanan & pembayaran**: nota pesanan, status Baru → Diproses → Selesai/Dibatalkan,
  DP/cicilan/pelunasan, sisa tagihan, dan serah produk.
- **Produksi**: perintah produksi (SPK) per item pesanan, beberapa batch per item,
  kebutuhan bahan dari BOM, serah bahan (stok keluar), progres CUTTING → SEWING →
  FINISHING → QC, dan laporan hasil produksi.
- **Pembelian & stok**: pembelian ke pemasok, penerimaan gudang (stok masuk),
  mutasi stok/kartu stok, dan peringatan stok minimum.
- **Dashboard & laporan**: ringkasan pesanan aktif, jatuh tempo, piutang, produksi
  berjalan, pesanan siap diserahkan, laporan penjualan/pembelian/kartu
  stok/produktivitas, ekspor CSV/PDF.
- **Dokumen FOD PDF**: Nota Pesanan, Bukti Pembayaran, Nota Pembelian, SPK,
  Laporan Hasil Produksi dan QC, serta Surat Jalan dan Tanda Terima.
- **Audit sederhana**: perubahan status penting dicatat ke `backend/storage/logs/audit-*.log`.

## Teknologi

| Lapisan  | Teknologi                                                        |
| -------- | ---------------------------------------------------------------- |
| Backend  | Laravel 12, PHP 8.3+, Sanctum, Eloquent, PHPUnit                  |
| Database | SQL Server 2022 (produksi), SQLite in-memory (pengujian)          |
| Frontend | React 19, Vite, React Router, TanStack Query                      |
| Deploy   | Docker, nginx, GitHub Actions (GHCR)                              |

## Struktur singkat

```text
Konveksi/
├── backend/                  Laravel API (prefix /api/v1)
│   ├── app/Http/Controllers/Api/
│   ├── app/Http/Requests/
│   ├── app/Http/Resources/
│   ├── app/Models/
│   ├── app/Policies/
│   ├── app/Services/         Aturan bisnis (total, stok, produksi, dokumen)
│   ├── app/Support/          AccessMatrix, SimplePdf, DocumentAccess, AuditLogger
│   ├── config/               Termasuk cors.php dan logging.php (kanal audit)
│   ├── database/migrations/
│   ├── database/seeders/
│   └── routes/api.php
├── frontend/                 React + Vite
│   └── src/
│       ├── components/       AppShell, DocumentButtons, Icons
│       ├── context/
│       ├── lib/              api, format, documents
│       └── pages/
├── docs/                     PRD, FOD, contoh nota
└── docker-compose.yml        SQL Server + inisialisasi database
```

## Menjalankan secara lokal

### 1. SQL Server

```bash
docker compose up -d
```

Service `sqlserver-init` membuat database `KonveksiSejahteraDB` otomatis setelah
SQL Server sehat.

### 2. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Backend berjalan di `http://localhost:8000` dan API di `http://localhost:8000/api/v1`.

Variabel penting pada `backend/.env`:

- `DB_*` untuk koneksi SQL Server.
- `CORS_ALLOWED_ORIGINS` untuk origin frontend yang diizinkan (pisahkan dengan koma).
  Produksi umumnya same-origin lewat nginx; nilai contoh sudah memuat Vite lokal.

### 3. Frontend

```bash
cd frontend
npm install
npm run dev
```

Buka `http://localhost:5173`. Vite meneruskan `/api` ke `http://127.0.0.1:8000`
(lihat `frontend/vite.config.js`). Untuk build produksi, atur `VITE_API_BASE_URL`
bila frontend dan backend berbeda origin.

### Akun demo (seeder)

Semua akun memakai password awal `Konveksi#2026`.

| Username | Peran    |
| -------- | -------- |
| `owner`  | owner    |
| `dewi`   | admin    |
| `budi`   | gudang   |
| `rudi`   | produksi |

Login menerima username **atau** email.

## API

Prefix seluruh endpoint: `/api/v1`. Semua endpoint selain login wajib memakai
header `Authorization: Bearer <token>`.

| Modul      | Endpoint utama                                                                                          |
| ---------- | ------------------------------------------------------------------------------------------------------- |
| Auth       | `POST auth/login`, `POST auth/logout`, `GET auth/me`                                                     |
| Master     | `apiResource customers`, `suppliers`, `employees`, `materials`, `products`; `products/{id}/variants`; `variants/{id}/bom` |
| Pesanan    | `apiResource orders`, `POST orders/{id}/cancel`, `GET orders/{id}/balance`, `GET orders/{id}/material-requirements`, `GET/POST orders/{id}/payments`, `POST orders/{id}/handover` |
| Produksi   | `POST order-items/{id}/production-orders`, `GET production-orders`, `GET production-orders/{id}/requirements`, `POST production-orders/{id}/issue-materials`, `POST production-orders/{id}/progress`, `PATCH progress/{id}` |
| Pembelian  | `apiResource purchases`, `POST purchases/{id}/receive`, `POST purchases/{id}/cancel`                    |
| Stok       | `GET stock-movements`, `GET materials/{id}/ledger`, `GET materials/low-stock`                           |
| Dokumen    | `GET documents/{type}/{id}` (PDF)                                                                        |
| Laporan    | `GET reports/sales`, `reports/purchases`, `reports/stock-card`, `reports/productivity`, `GET reports/{type}/export?format=csv|pdf` |
| Akses      | `apiResource users`, `PUT users/{id}/roles`, `GET roles`                                                 |

### Jenis dokumen PDF

| `type`           | Sumber data        | Peran yang boleh membaca           |
| ---------------- | ------------------ | ---------------------------------- |
| `nota-pesanan`   | ID pesanan         | owner, admin                       |
| `bukti-bayar`    | ID pesanan         | owner, admin                       |
| `nota-pembelian` | ID pembelian       | owner, admin, gudang               |
| `spk`            | ID perintah produksi | owner, admin, produksi, gudang   |
| `hasil-produksi` | ID perintah produksi | owner, admin, produksi           |
| `surat-jalan`    | ID pesanan         | owner, admin                       |

Respons error memakai amplop seragam `{ "message": "...", "errors": { ... } }`
(422 untuk validasi/aturan bisnis, 403 untuk akses, 404 untuk data/dokumen tidak ada).

## Validasi dan pengujian

```bash
# Backend
cd backend
vendor/bin/pint --test
php artisan test

# Frontend
cd frontend
npm run lint
npm run build
```

CI menjalankan Pint, PHPUnit (SQLite), ESLint, dan build Vite
(`.github/workflows/quality.yml`). Rilis image ke GHCR dan deploy produksi diatur
di `.github/workflows/release.yml`.

## Catatan driver SQL Server

- Konfigurasi utama ada di `backend/.env` (`DB_CONNECTION=sqlsrv`).
- Jika ekstensi PHP `pdo_sqlsrv` tersedia, Laravel memakainya otomatis.
- Jika `pdo_sqlsrv` belum terpasang, konfigurasi memakai `pdo_odbc` + **ODBC Driver 18
  for SQL Server**.
- Paksa salah satu driver dengan `DB_ODBC=false` (pdo_sqlsrv) atau `DB_ODBC=true` (ODBC).
- Pada Windows, pastikan **ODBC Driver 18 for SQL Server** dan ekstensi
  `pdo_sqlsrv`/`sqlsrv` sesuai versi PHP terpasang.
