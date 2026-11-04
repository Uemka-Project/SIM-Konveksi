# PRD — Sistem Informasi Manajemen Produksi Konveksi (Konveksi Sejahtera)

| | |
|---|---|
| Versi | 0.2 (draft implementasi, SQL final tahap 1) |
| Tanggal | 4 November 2026 |
| Konteks | Tugas I SMBD (ADDIE, SQL Server). Konveksi Sejahtera adalah UMKM fiktif |
| Owner teknis | Ismail (202553003) |
| Stack | Laravel (API) · React (SPA) · Microsoft SQL Server |

---

## 1. Ringkasan

Konveksi Sejahtera bekerja dengan model **Make-to-Order**: produksi dimulai setelah ada pesanan pelanggan. Sistem ini menggantikan pencatatan manual (nota kertas, buku stok) dengan aplikasi yang menangani pesanan, kebutuhan bahan (BOM), pembelian bahan, stok, progres produksi, dan pembayaran.

**Tujuan produk**
1. Semua pesanan, pembayaran, dan progres produksi tercatat dan bisa ditelusuri.
2. Stok bahan baku selalu akurat lewat buku besar pergerakan stok (*stock ledger*).
3. Pemilik bisa melihat bahan yang hampir habis, pesanan yang terlambat, dan sisa tagihan.

**Bukan tujuan (v1)**: akuntansi lengkap/pajak, e-commerce publik, integrasi payment gateway, aplikasi mobile native, multi-cabang.

## 2. Pengguna dan Peran

Peran disimpan di tabel `roles`, dan satu user bisa punya banyak peran lewat `user_roles`.

| Role | Siapa | Hak utama |
|---|---|---|
| `owner` | Pemilik | Semua modul, laporan, kelola user |
| `admin` | Admin/kasir | Pelanggan, pesanan, pembayaran, pembelian |
| `produksi` | Kepala produksi | Perintah produksi, progres, pergerakan stok keluar |
| `gudang` | Staf gudang | Bahan, pembelian diterima, stok masuk |

Matriks hak akses rinci ada di bagian 7.

## 3. Model Data

18 tabel, mengikuti ER-Diagram Chen versi terbaru dan DDL SQL Server (`01_ddl.sql`). Primary key memakai `IDENTITY` (kecuali PK gabungan).

| Kelompok | Tabel |
|---|---|
| Master | customers, suppliers, employees, materials, products, product_variants, variant_materials |
| Transaksi | orders, order_details, payments, purchases, purchase_details, production_orders, production_progress, stock_movements |
| Akses | users, roles, user_roles |

**Relasi (1 : M kecuali dinyatakan lain)**: customers→orders, orders→payments, orders→order_details, products→product_variants, product_variants→order_details, product_variants→variant_materials, materials→variant_materials, order_details→production_orders, production_orders→production_progress, employees→production_progress, employees→users (1:1, opsional), users→user_roles, roles→user_roles, suppliers→purchases, purchases→purchase_details, materials→purchase_details, materials→stock_movements, purchase_details→stock_movements, production_orders→stock_movements.

**Atribut turunan** (tidak diinput manual): `order_details.subtotal`, `purchase_details.subtotal` (= quantity × unit_price), `orders.total_amount`, `purchases.total_amount` (= jumlah subtotal), dan `materials.current_stock` (= total MASUK − total KELUAR).

### 3.1 Keputusan yang sudah diterapkan di SQL

| # | Keputusan | Dampak |
|---|---|---|
| D1 | `order_details` 1 : **M** `production_orders` (mengikuti ER-Diagram terbaru). Constraint `UQ_po_order_detail` dihapus. | Satu item pesanan bisa dipecah ke beberapa batch produksi. Validasi aplikasi: jumlah `planned_quantity` seluruh batch ≤ `order_details.quantity` (juga diperiksa di `04_verifikasi.sql`). |
| D2 | Tabel `users`, `roles`, `user_roles` sudah ada di DDL (18 tabel). | Peran dan akun awal disediakan oleh `03_seed_akses.sql`. |
| D3 | `users.employee_id` boleh NULL; bila terisi harus unik (*filtered unique index*). | Akun `owner` tanpa data karyawan; relasi employees 1:1 users tetap terjaga. |

## 4. Rancangan Database untuk Laravel

**Prinsip**: skema di SQL Server tetap menjadi sumber kebenaran. Migrasi Laravel meniru DDL yang sudah divalidasi, termasuk CHECK constraint, dengan `DB::statement` bila Blueprint tidak mendukung.

- Driver: `sqlsrv` (paket `pdo_sqlsrv` + `sqlsrv`, ODBC Driver 18).
- Nama tabel dan kolom tetap snake_case persis seperti ERD. Pakai `$table` dan `$primaryKey` eksplisit di Model karena PK bukan `id`.
- `$timestamps = false` pada tabel sesuai DDL. Bila butuh audit, tambah `created_at/updated_at` lewat migrasi lanjutan.
- Tabel pivot `variant_materials` dan `user_roles` punya PK gabungan, dimodelkan sebagai `belongsToMany` dengan pivot (`quantity_per_unit`, `assigned_at`), bukan Model dengan `id`.

### 4.1 Berkas SQL dan urutan eksekusi

Jalankan berurutan di SQL Server Management Studio (atau `sqlcmd`):

| Urutan | File | Fungsi |
|---|---|---|
| 1 | `01_ddl.sql` | Membuat database `KonveksiSejahteraDB` dan 18 tabel (PK, FK, UNIQUE, CHECK). Tidak menghapus database yang sudah ada. |
| 2 | `02_dml_contoh.sql` | Data contoh: 5 pelanggan, 5 pemasok, 5 karyawan, bahan, produk dan varian, BOM, 5 pesanan beserta pembayaran, 4 pembelian, 6 perintah produksi dengan progres, dan mutasi stok. Saldo `materials.current_stock` disinkronkan dari `stock_movements`. |
| 3 | `03_seed_akses.sql` | 4 peran (`owner`, `admin`, `produksi`, `gudang`) dan 4 akun awal: `owner` (tanpa karyawan), `dewi` (admin), `budi` (gudang), `rudi` (produksi). Wajib setelah file 2 karena mengaitkan akun ke `employees`. |
| 4 | `04_verifikasi.sql` | Kueri bukti dan pemeriksaan: isi dan jumlah tiap tabel, kebutuhan bahan dari BOM, bahan di bawah minimum, rekonsiliasi stok, sisa tagihan, validasi total pesanan, batas rencana produksi, dan uji constraint. |

**Akun awal.** Kata sandi semua akun adalah `Konveksi#2026` dan **wajib diganti** sebelum dipakai di luar pengembangan. Nilainya disimpan sebagai hash bcrypt (`$2y$`), jadi langsung cocok dengan `Hash::check` Laravel. Login via Laravel memakai `username` atau `email`.

**Hubungan dengan Laravel.** `01_ddl.sql` menjadi acuan migrasi. File 2 dan 3 menjadi seeder (`DatabaseSeeder` memanggil seeder master, transaksi, lalu akses, sesuai urutan di atas). File 4 dijalankan manual sebagai bukti untuk laporan dan sebagai acuan uji.

**Berkas yang sengaja ditunda ke tahap 2:** `view` laporan, `stored procedure` (terima pembelian, mulai produksi), `trigger` penjaga stok dan total, serta pengaturan login database. Selama tahap 1, aturan itu dijalankan oleh service Laravel.

**Nilai domain (dari CHECK constraint)**

| Kolom | Nilai |
|---|---|
| orders.status | Baru, Diproses, Selesai, Dibatalkan |
| purchases.status | Dipesan, Diterima, Dibatalkan |
| production_orders.status | Menunggu, Berjalan, Selesai |
| production_progress.stage_code | CUTTING, SEWING, FINISHING, QC |
| production_progress.status | Proses, Selesai |
| stock_movements.movement_type | MASUK, KELUAR |
| payments.payment_method | Tunai, Transfer, QRIS |
| payments.payment_type | DP, Cicilan, Pelunasan |

**Aturan integritas penting**
- `stock_movements` wajib punya tepat satu sumber: `purchase_detail_id` (untuk MASUK) **atau** `production_order_id` (untuk KELUAR). Constraint `CK_sm_source` sudah ada.
- `materials.current_stock >= 0`, jadi pengeluaran bahan yang melebihi stok harus ditolak.
- `due_date >= order_date`; `target_date >= start_date`; progres Selesai wajib punya `completed_at >= started_at`.

## 5. Kebutuhan Fungsional

Prioritas: **P0** = wajib v1, **P1** = sebaiknya ada, **P2** = nanti.

### 5.1 Autentikasi dan Akses (P0)
- FR-A1 Login username/email + password; token Sanctum (SPA cookie atau bearer).
- FR-A2 Logout dan cek sesi (`/api/me` mengembalikan user + roles).
- FR-A3 Kelola user dan role (owner): buat, nonaktifkan (`is_active`), tetapkan peran.
- FR-A4 Otorisasi per endpoint lewat Policy/Gate berbasis role.

### 5.2 Data Master (P0)
- FR-M1 CRUD customers, suppliers, employees, materials, products.
- FR-M2 Varian produk (`product_variants`: size, color, sku unik) di bawah produk.
- FR-M3 BOM: atur `variant_materials` per varian (bahan + `quantity_per_unit` > 0).
- FR-M4 Pencarian, filter, dan paginasi di semua daftar. Tidak boleh hapus data yang sudah dipakai transaksi (ubah jadi nonaktif untuk employees; tolak untuk lainnya).

### 5.3 Pesanan dan Pembayaran (P0)
- FR-O1 Buat pesanan: pilih pelanggan, tanggal, due_date, lalu item (varian + qty + harga satuan). `subtotal` dan `total_amount` dihitung server.
- FR-O2 Ubah status pesanan: Baru → Diproses → Selesai, atau Dibatalkan. Pesanan Selesai tidak bisa diedit.
- FR-O3 Catat pembayaran (DP, Cicilan, Pelunasan; metode Tunai/Transfer/QRIS). Total bayar tidak boleh melebihi total pesanan.
- FR-O4 Tampilkan sisa tagihan dan status lunas per pesanan.
- FR-O5 Cetak/unduh nota pesanan (PDF) (P1).

### 5.4 Produksi (P0)
- FR-P1 Buat perintah produksi dari item pesanan (qty rencana, tanggal mulai, target). Sesuai keputusan D1, satu item bisa punya beberapa batch.
- FR-P2 Hitung kebutuhan bahan dari BOM (qty × `quantity_per_unit`) dan tampilkan kecukupan stok sebelum produksi dimulai.
- FR-P3 Catat progres per tahap (CUTTING, SEWING, FINISHING, QC): karyawan, mulai, selesai, qty selesai.
- FR-P4 Saat produksi Berjalan, sistem membuat `stock_movements` KELUAR sesuai kebutuhan BOM (transaksi atomik).
- FR-P5 Status produksi menjadi Selesai bila semua tahap Selesai; bila seluruh batch item selesai, tawarkan menandai pesanan Selesai.

### 5.5 Pembelian dan Stok (P0)
- FR-S1 Buat pembelian bahan ke pemasok (item bahan, qty, harga). Status Dipesan → Diterima / Dibatalkan.
- FR-S2 Saat pembelian ditandai Diterima, sistem membuat `stock_movements` MASUK per item (atomik).
- FR-S3 Daftar mutasi stok (kartu stok) per bahan, filter tanggal dan tipe.
- FR-S4 Peringatan bahan di bawah `minimum_stock` di dashboard.
- FR-S5 `current_stock` dijaga konsisten oleh service (atau trigger SQL Server pada tahap 2); jangan diedit langsung dari UI.

### 5.6 Dashboard dan Laporan (P1)
- FR-R1 Dashboard: pesanan aktif, pesanan mendekati/melewati due_date, bahan di bawah minimum, produksi berjalan, piutang.
- FR-R2 Laporan: penjualan per periode, pembelian per periode, kartu stok, produktivitas karyawan per tahap. Ekspor CSV/PDF.

## 6. Kebutuhan Non-Fungsional

| Aspek | Target |
|---|---|
| Keamanan | Password di-hash (bcrypt/argon2), semua endpoint di balik auth, validasi input server-side (FormRequest), query lewat Eloquent/binding (anti SQL injection), CORS terbatas |
| Konsistensi | Operasi yang menyentuh stok dan total memakai `DB::transaction` |
| Kinerja | Daftar paginasi (default 20), eager loading untuk menghindari N+1, respons daftar < 500 ms pada data seed |
| Bahasa/UX | Antarmuka Bahasa Indonesia, format Rupiah dan tanggal lokal (`dd/MM/yyyy`) |
| Kompatibilitas | Browser modern (Chrome, Edge, Firefox), responsif sampai lebar tablet |
| Observability | Log error terstruktur, `audit` sederhana untuk perubahan status (P2) |

## 7. Matriks Hak Akses

C = create, R = read, U = update, D = delete, `—` = tanpa akses.

| Modul | owner | admin | produksi | gudang |
|---|---|---|---|---|
| Customers | CRUD | CRUD | R | — |
| Suppliers | CRUD | R | — | CRUD |
| Employees | CRUD | R | R | — |
| Materials, products, varian, BOM | CRUD | R | CRU (BOM) | CRU (materials) |
| Orders, order items | CRUD | CRU | R | — |
| Payments | CRUD | CR | — | — |
| Purchases | CRUD | R | — | CRU |
| Production orders, progress | CRUD | R | CRU | R |
| Stock movements | R | R | R (KELUAR via produksi) | R (MASUK via pembelian) |
| Users, roles | CRUD | — | — | — |
| Laporan | R | R | R (produksi) | R (stok) |

## 8. Arsitektur dan Kontrak API

```
React SPA (Vite) ──HTTPS/JSON──▶ Laravel API (Sanctum) ──▶ SQL Server
```

**Backend (Laravel 11/12, PHP 8.3)**
- Struktur: `Models` → `Services` (aturan bisnis: hitung total, mutasi stok, validasi batch produksi) → `Http/Controllers` tipis → `Http/Resources` (JSON) → `FormRequests` (validasi) → `Policies`.
- Prefix `/api/v1`. Respons error seragam: `{ "message": "...", "errors": { "field": ["..."] } }` (422 untuk validasi).
- Pengujian: PHPUnit/Pest untuk Service (total, stok, batch) dan endpoint kritis.

**Frontend (React 18 + TypeScript, Vite)**
- Routing: React Router. Data: TanStack Query. Form: React Hook Form + Zod. UI: pilihan tim (Tailwind + shadcn/ui disarankan).
- Struktur: `features/<modul>/{api,components,pages}` agar tiap modul mandiri.
- Guard rute berdasarkan `roles` dari `/api/v1/me`.

**Endpoint utama (ringkas)**

| Modul | Endpoint |
|---|---|
| Auth | `POST /auth/login`, `POST /auth/logout`, `GET /me` |
| Master | `apiResource`: `customers`, `suppliers`, `employees`, `materials`, `products`; `products/{id}/variants`; `variants/{id}/bom` |
| Order | `apiResource orders`, `POST orders/{id}/status`, `POST orders/{id}/payments`, `GET orders/{id}/balance` |
| Produksi | `POST order-items/{id}/production-orders`, `GET production-orders/{id}/requirements`, `POST production-orders/{id}/start`, `POST production-orders/{id}/progress`, `PATCH progress/{id}` |
| Pembelian | `apiResource purchases`, `POST purchases/{id}/receive` |
| Stok | `GET stock-movements`, `GET materials/{id}/ledger`, `GET materials/low-stock` |
| Akses | `apiResource users`, `PUT users/{id}/roles`, `GET roles` |
| Laporan | `GET reports/sales`, `reports/purchases`, `reports/stock-card`, `reports/productivity` |

## 9. Layar Utama (React)

1. Login
2. Dashboard
3. Pelanggan / Pemasok / Karyawan / Bahan (daftar + form)
4. Produk → varian → BOM (halaman detail dengan tab)
5. Pesanan: daftar, form buat (item dinamis), detail (item, pembayaran, status, produksi)
6. Produksi: daftar perintah, detail (kebutuhan bahan, timeline tahap CUTTING → QC)
7. Pembelian: daftar, form, tombol Terima
8. Stok: daftar bahan (indikator minimum), kartu stok
9. Laporan
10. Kelola user dan peran (owner)

## 10. Rencana Implementasi

| Fase | Isi | Hasil |
|---|---|---|
| 0. Fondasi | Repo, Laravel + sqlsrv, React + Vite, CI dasar, `.env.example` | Proyek jalan, koneksi DB hijau |
| 1. Database | Jalankan `01`–`04` di SQL Server, lalu terjemahkan ke migrasi dan seeder Laravel | Skema identik dengan DDL, data contoh dan akun awal siap |
| 2. Auth + Master | FR-A*, FR-M* + layar 1–4 | Login, role guard, CRUD master |
| 3. Pesanan + Bayar | FR-O* + layar 5 | Alur pesanan sampai lunas |
| 4. Pembelian + Stok | FR-S* + layar 7–8 | Stok akurat via ledger |
| 5. Produksi | FR-P* + layar 6 | Batch, progres, bahan keluar otomatis |
| 6. Dashboard + Laporan | FR-R* + layar 2, 9 | Ringkasan dan ekspor |
| 7. Pengujian + Dokumentasi | Uji black-box (sesuai pembagian tim), screenshot SSMS, panduan pengguna | Siap masuk laporan akhir |

Pembagian tim mengikuti laporan: Ismail (DB, backend, frontend, integrasi), Ninda (analisis, flowchart, dokumentasi), Aulia (wireframe UI, pengujian, panduan pengguna).

## 11. Kriteria Penerimaan (v1)

- [ ] Skema di SQL Server dibuat dari migrasi dan setara dengan DDL (18 tabel, semua constraint).
- [ ] Alur utama lolos end-to-end: buat pesanan → bayar DP → beli bahan → terima (stok naik) → mulai produksi (stok turun) → selesaikan 4 tahap → pelunasan → pesanan Selesai.
- [ ] Stok tidak pernah negatif; percobaan pengeluaran berlebih ditolak dengan pesan jelas.
- [ ] `total_amount` dan `subtotal` selalu sama dengan hasil hitung dari item.
- [ ] Setiap role hanya bisa mengakses modul sesuai matriks (diuji).
- [ ] Seeder menghasilkan data contoh yang sama dengan `02_dml_contoh.sql`, dan login berhasil dengan akun dari `03_seed_akses.sql`.
- [ ] Skenario uji black-box utama lulus dan terdokumentasi.

## 12. Risiko dan Asumsi

| Risiko / asumsi | Mitigasi |
|---|---|
| Driver `sqlsrv` di Linux/Docker butuh ODBC Driver 18 | Siapkan Dockerfile dengan driver terpasang sejak fase 0 |
| Migrasi Blueprint tidak mendukung CHECK/filtered index | Pakai `DB::statement` untuk bagian itu |
| `current_stock` bisa menyimpang bila ada jalur tulis lain | Satu-satunya jalur: service mutasi stok; tahap 2 tambah trigger/prosedur tersimpan |
| Relasi 1:M pada production_orders (D1) membuka kemungkinan total batch melebihi qty pesanan | Validasi di service dan kueri nomor 10 di `04_verifikasi.sql` |
| Waktu terbatas (tugas kuliah) | Kerjakan P0 dulu; P1/P2 hanya bila waktu ada |
| Asumsi: satu cabang, satu mata uang (IDR), tanpa pajak | Di luar cakupan v1 |

## 13. Lampiran

- ER-Diagram Chen (18 entitas) dan ERD Relational Schema: set SVG pada paket diagram.
- `01_ddl.sql`, `02_dml_contoh.sql`, `03_seed_akses.sql`, `04_verifikasi.sql`: lihat bagian 4.1 untuk fungsi dan urutan eksekusi.
