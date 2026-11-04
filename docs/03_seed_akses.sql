-- =====================================================
-- File : 03_seed_akses.sql (data awal peran dan akun pengguna)
-- Jalankan SETELAH 02_dml_contoh.sql (butuh data employees)
-- Kata sandi awal semua akun: Konveksi#2026  (WAJIB diganti)
-- Hash di bawah adalah bcrypt ($2y$), kompatibel dengan Hash::check Laravel
-- =====================================================
USE KonveksiSejahteraDB;
GO

-- 1. roles
INSERT INTO roles (role_name, description)
VALUES
(N'owner',    N'Pemilik: semua modul, laporan, dan kelola pengguna'),
(N'admin',    N'Admin/kasir: pelanggan, pesanan, pembayaran, pembelian'),
(N'produksi', N'Kepala produksi: perintah produksi dan progres'),
(N'gudang',   N'Staf gudang: bahan, penerimaan pembelian, stok');
GO

-- 2. users (employee_id diisi dari data karyawan; owner tanpa data karyawan)
INSERT INTO users (employee_id, username, password_hash, email, is_active)
VALUES
(NULL, N'owner', N'$2y$10$87GvQoQ6GF09mIIs07w0KOW1rXT2zjj.f88QazQvtcbwIZ1BhZ5eG', N'owner@konveksisejahtera.test', 1),
((SELECT employee_id FROM employees WHERE name = N'Dewi Lestari'),  N'dewi',  N'$2y$10$87GvQoQ6GF09mIIs07w0KOW1rXT2zjj.f88QazQvtcbwIZ1BhZ5eG', N'dewi@konveksisejahtera.test', 1),
((SELECT employee_id FROM employees WHERE name = N'Budi Santoso'),  N'budi',  N'$2y$10$87GvQoQ6GF09mIIs07w0KOW1rXT2zjj.f88QazQvtcbwIZ1BhZ5eG', N'budi@konveksisejahtera.test', 1),
((SELECT employee_id FROM employees WHERE name = N'Rudi Hartono'),  N'rudi',  N'$2y$10$87GvQoQ6GF09mIIs07w0KOW1rXT2zjj.f88QazQvtcbwIZ1BhZ5eG', N'rudi@konveksisejahtera.test', 1);
GO

-- 3. user_roles
INSERT INTO user_roles (user_id, role_id)
SELECT u.user_id, r.role_id
FROM (VALUES (N'owner', N'owner'), (N'dewi', N'admin'), (N'budi', N'gudang'), (N'rudi', N'produksi')) AS x(username, role_name)
JOIN users u ON u.username = x.username
JOIN roles r ON r.role_name = x.role_name;
GO
