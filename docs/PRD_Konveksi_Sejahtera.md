# PRD — Sistem Informasi Manajemen Produksi Konveksi (Konveksi Sejahtera)

| | |
|---|---|
| Versi | 0.3 (disesuaikan dengan Document Flowchart / FOD) |
| Tanggal | 6 November 2026 |
| Konteks | Tugas I SMBD (ADDIE, SQL Server). Konveksi Sejahtera adalah UMKM fiktif. Alur proses mengacu pada Document Flowchart (FOD) proses produksi |
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

Peran disimpan di tabel `roles`, dan satu user bisa punya banyak peran lewat `user_roles`. Lajur pada FOD menjadi dasar pembagian peran.

| Role | Siapa | Tanggung jawab (sesuai lajur FOD) |
|---|---|---|
| `owner` | Pemilik/Manajer | Semua modul, memantau laporan manajemen, kelola user |
| `admin` | Admin/kasir | Mencatat pelanggan dan pesanan, menerima pembayaran, membuat pembelian ke pemasok, membuat perintah produksi (SPK), cek tagihan, serah produk, menyusun laporan |
| `gudang` | Staf gudang | Melihat daftar kebutuhan bahan dan kecukupan stok, menerima bahan (stok masuk), menyerahkan bahan ke produksi (stok keluar) |
| `produksi` | Kepala produksi | Mengerjakan dan mencatat progres cutting, sewing, finishing, QC |

Pelanggan dan pemasok adalah pihak luar: tidak punya akun dan tidak login.

Matriks hak akses rinci ada di bagian 7.

### 2.1 Alur bisnis (mengacu FOD)

| Langkah FOD | Pelaku | Perilaku sistem | Dokumen | FR |
|---|---|---|---|---|
| 1. Pelanggan mengajukan pesanan | Pelanggan → admin | Admin mencatat pelanggan dan pesanan; harga dan total dihitung otomatis (status **Baru**) | Nota Pesanan | FR-O1 |
| 2. Pelanggan setuju harga dan bayar DP? | Pelanggan | Bila tidak setuju: pesanan **Dibatalkan**. Bila setuju: admin mencatat pembayaran DP, status menjadi **Diproses** | Bukti Pembayaran DP | FR-O2, FR-O3 |
| 3. Hitung kebutuhan bahan (BOM × qty) | Admin | Sistem menghitung kebutuhan bahan per pesanan dari BOM dan menampilkan kecukupan stok | Daftar Kebutuhan Bahan | FR-P2 |
| 4. Stok cukup? Tidak: beli bahan | Admin → pemasok | Admin membuat pembelian (status **Dipesan**) | Nota Pembelian | FR-S1 |
| 5. Terima bahan, catat stok masuk | Gudang | Pembelian ditandai **Diterima**, sistem membuat mutasi MASUK per item | Nota Pembelian | FR-S2 |
| 6. Buat perintah produksi | Admin | Perintah produksi (SPK) dibuat per item pesanan, boleh lebih dari satu batch. Syarat: pesanan sudah ada DP | SPK | FR-P1 |
| 7. Keluarkan bahan, catat stok keluar | Gudang | Gudang menyerahkan bahan, sistem membuat mutasi KELUAR sesuai BOM, produksi menjadi **Berjalan** | SPK | FR-P4 |
| 8. Cutting, sewing, finishing, catat progres | Produksi | Progres per tahap dicatat (karyawan, waktu, qty) | Laporan Hasil Produksi dan QC | FR-P3 |
| 9. Lolos QC? Tidak: ulang | Produksi | Pengerjaan ulang dicatat sebagai baris progres baru pada tahap terkait. QC Selesai berarti lolos; produksi menjadi **Selesai** bila keempat tahap Selesai | Laporan Hasil Produksi dan QC | FR-P3, FR-P5 |
| 10. Cek sisa tagihan | Admin | Sistem menampilkan total, sudah dibayar, dan sisa | | FR-O4 |
| 11. Lunas? Tidak: pelanggan bayar pelunasan | Pelanggan → admin | Admin mencatat Cicilan (opsional) dan Pelunasan | Bukti Pembayaran | FR-O3 |
| 12. Serahkan produk | Admin | Syarat: semua produksi Selesai dan tagihan lunas. Pesanan menjadi **Selesai** | Surat Jalan dan Tanda Terima | FR-O6 |
| 13. Susun laporan, pemilik memantau | Admin → owner | Laporan penjualan, pembelian, stok, produksi | Laporan Manajemen | FR-R1, FR-R2 |

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
- FR-O2 Status pesanan: **Baru** (nota dibuat) → **Diproses** (otomatis saat DP pertama dicatat) → **Selesai** (saat produk diserahkan). **Dibatalkan** bila pelanggan tidak setuju harga atau tidak membayar DP, hanya selama belum ada produksi yang berjalan. Pesanan Selesai atau Dibatalkan tidak bisa diedit.
- FR-O3 Catat pembayaran (DP, Cicilan, Pelunasan; metode Tunai/Transfer/QRIS). Cicilan opsional. Total bayar tidak boleh melebihi total pesanan. Pembayaran pertama berjenis DP.
- FR-O4 Tampilkan sisa tagihan dan status lunas per pesanan.
- FR-O5 Cetak/unduh dokumen FOD dalam PDF (P1): Nota Pesanan, Bukti Pembayaran, Nota Pembelian, SPK, Laporan Hasil Produksi dan QC, Surat Jalan dan Tanda Terima.
- FR-O6 Serah produk (admin): hanya bila semua perintah produksi pesanan Selesai dan sisa tagihan 0. Aksi ini mengubah status pesanan menjadi Selesai dan menyiapkan Surat Jalan dan Tanda Terima.

### 5.4 Produksi (P0)
- FR-P1 Admin membuat perintah produksi (SPK) dari item pesanan (qty rencana, tanggal mulai, target). Syarat: pesanan sudah ada DP dan berstatus Diproses. Sesuai keputusan D1, satu item bisa punya beberapa batch.
- FR-P2 Sistem menghitung kebutuhan bahan dari BOM (qty × `quantity_per_unit`) per pesanan dan per perintah produksi, lalu menampilkan kecukupan stok serta bahan yang kurang (Daftar Kebutuhan Bahan). Dipakai admin untuk memutuskan pembelian dan oleh gudang sebelum menyerahkan bahan.
- FR-P3 Produksi mencatat progres per tahap (CUTTING, SEWING, FINISHING, QC): karyawan, mulai, selesai, qty selesai. Bila QC tidak lolos, pengerjaan ulang dicatat sebagai baris progres baru pada tahap terkait (tidak ada status gagal di skema).
- FR-P4 Gudang menyerahkan bahan untuk perintah produksi berstatus Menunggu. Sistem membuat `stock_movements` KELUAR sesuai kebutuhan BOM dan mengubah status menjadi Berjalan dalam satu transaksi. Ditolak bila stok kurang.
- FR-P5 Status produksi menjadi Selesai bila keempat tahap Selesai. Bila seluruh perintah produksi pesanan Selesai, sistem memberi tahu admin bahwa pesanan siap dicek tagihan dan diserahkan (FR-O6).

### 5.5 Pembelian dan Stok (P0)
- FR-S1 Admin membuat pembelian bahan ke pemasok (item bahan, qty, harga) dari Daftar Kebutuhan Bahan. Status Dipesan → Diterima / Dibatalkan.
- FR-S2 Gudang menandai pembelian Diterima. Sistem membuat `stock_movements` MASUK per item (atomik).
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
| Suppliers | CRUD | CRUD | — | R |
| Employees | CRUD | R | R | — |
| Products, varian, BOM | CRUD | R | CRU (BOM) | R |
| Materials | CRUD | R | R | CRU |
| Orders, order items | CRUD | CRU (batal dan serah produk) | R | R |
| Payments | CRUD | CR | — | — |
| Purchases | CRUD | CRU (buat, batal) | — | RU (terima) |
| Production orders | CRUD | CRU (buat SPK) | RU (status selesai) | RU (serahkan bahan) |
| Production progress | CRUD | R | CRU | R |
| Stock movements | R | R | R | CR (MASUK dari terima, KELUAR dari serah bahan) |
| Dokumen cetak | R | R | R (SPK, laporan produksi) | R (nota pembelian, SPK) |
| Users, roles | CRUD | — | — | — |
| Laporan | R | R | R (produksi) | R (stok) |

Mutasi stok tidak dibuat manual dari menu: hanya lewat aksi Terima pembelian dan Serahkan bahan, supaya sumbernya selalu valid.

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
| Order | `apiResource orders`, `POST orders/{id}/cancel`, `POST orders/{id}/payments`, `GET orders/{id}/balance`, `GET orders/{id}/material-requirements`, `POST orders/{id}/handover` |
| Produksi | `POST order-items/{id}/production-orders` (admin), `GET production-orders/{id}/requirements`, `POST production-orders/{id}/issue-materials` (gudang), `POST production-orders/{id}/progress` (produksi), `PATCH progress/{id}` |
| Pembelian | `apiResource purchases`, `POST purchases/{id}/receive` (gudang) |
| Stok | `GET stock-movements`, `GET materials/{id}/ledger`, `GET materials/low-stock` |
| Akses | `apiResource users`, `PUT users/{id}/roles`, `GET roles` |
| Dokumen | `GET documents/{type}/{id}` (PDF: nota-pesanan, bukti-bayar, nota-pembelian, spk, hasil-produksi, surat-jalan) |
| Laporan | `GET reports/sales`, `reports/purchases`, `reports/stock-card`, `reports/productivity` |

## 9. Layar Utama (React)

1. Login
2. Dashboard
3. Pelanggan / Pemasok / Karyawan / Bahan (daftar + form)
4. Produk → varian → BOM (halaman detail dengan tab)
5. Pesanan: daftar, form buat (item dinamis), detail (item, pembayaran dan sisa tagihan, kebutuhan bahan, produksi, tombol Serah Produk)
6. Produksi: daftar perintah, detail (kebutuhan bahan, tombol Serahkan Bahan untuk gudang, timeline tahap CUTTING → QC untuk produksi)
7. Pembelian: daftar, form (admin), tombol Terima (gudang)
8. Stok: daftar bahan (indikator minimum), kartu stok
9. Laporan
10. Kelola user dan peran (owner)

## 10. Rencana Implementasi

| Fase | Isi | Hasil |
|---|---|---|
| 0. Fondasi | Repo, Laravel + sqlsrv, React + Vite, CI dasar, `.env.example` | Proyek jalan, koneksi DB hijau |
| 1. Database | Jalankan `01`–`04` di SQL Server, lalu terjemahkan ke migrasi dan seeder Laravel | Skema identik dengan DDL, data contoh dan akun awal siap |
| 2. Auth + Master | FR-A*, FR-M* + layar 1–4 | Login, role guard, CRUD master |
| 3. Pesanan + Bayar | FR-O1–O4 + layar 5 | Alur pesanan sampai DP dan lunas |
| 4. Pembelian + Stok | FR-S* + layar 7–8 | Stok akurat via ledger |
| 5. Produksi + Serah | FR-P*, FR-O5–O6 + layar 6 | SPK, serah bahan (stok keluar), progres, serah produk |
| 6. Dashboard + Laporan | FR-R* + layar 2, 9 | Ringkasan dan ekspor |
| 7. Pengujian + Dokumentasi | Uji black-box (sesuai pembagian tim), screenshot SSMS, panduan pengguna | Siap masuk laporan akhir |

Pembagian tim mengikuti laporan: Ismail (DB, backend, frontend, integrasi), Ninda (analisis, flowchart, dokumentasi), Aulia (wireframe UI, pengujian, panduan pengguna).

## 11. Kriteria Penerimaan (v1)

- [ ] Skema di SQL Server dibuat dari migrasi dan setara dengan DDL (18 tabel, semua constraint).
- [ ] Alur FOD lolos end-to-end dengan peran yang sesuai: admin buat pesanan → bayar DP → sistem hitung kebutuhan bahan → admin buat pembelian → gudang terima (stok naik) → admin buat SPK → gudang serahkan bahan (stok turun) → produksi selesaikan 4 tahap → admin cek tagihan dan terima pelunasan → admin serah produk → pesanan Selesai.
- [ ] SPK ditolak untuk pesanan tanpa DP; serah produk ditolak bila produksi belum selesai atau tagihan belum lunas.
- [ ] Pesanan yang tidak disetujui pelanggan bisa dibatalkan (status Dibatalkan) sebelum produksi berjalan.
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
| Dokumen FOD (nota, SPK, surat jalan) hanya dibuat sebagai PDF dari data yang ada, tanpa tabel khusus | Tanggal serah produk tidak tersimpan; bila perlu, tambah kolom `delivered_date` di `orders` (opsional, ERD tidak berubah secara relasi) |
| Pembayaran ke pemasok dan retur produksi tidak dimodelkan | Di luar cakupan v1 |
| Waktu terbatas (tugas kuliah) | Kerjakan P0 dulu; P1/P2 hanya bila waktu ada |
| Asumsi: satu cabang, satu mata uang (IDR), tanpa pajak | Di luar cakupan v1 |

## 13. Lampiran

- ER-Diagram Chen (18 entitas) dan ERD Relational Schema: set SVG pada paket diagram.
- `01_ddl.sql`, `02_dml_contoh.sql`, `03_seed_akses.sql`, `04_verifikasi.sql`: lihat bagian 4.1 untuk fungsi dan urutan eksekusi.