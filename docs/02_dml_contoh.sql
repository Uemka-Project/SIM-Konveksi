-- =====================================================
-- File : 02_dml_contoh.sql (DML: data uji, minimal 5 record per tabel)
-- Seluruh data adalah data simulasi/fiktif.
-- Jalankan setelah 01_ddl.sql. ID identity dimulai dari 1,
-- sehingga urutan INSERT di bawah menentukan nilai FK.
-- =====================================================
USE KonveksiSejahteraDB;
GO

-- 1. customers
INSERT INTO customers (name, phone, address, email)
VALUES
(N'Toko Berkah Sandang', N'081200000001', N'Jl. Sunan Muria No. 12, Kudus', N'berkahsandang@example.test'),
(N'CV Karya Mandiri', N'081200000002', N'Jl. Pemuda No. 45, Jepara', N'karyamandiri@example.test'),
(N'Koperasi Tani Makmur', N'081200000003', N'Jl. Sultan Fatah No. 8, Demak', N'tanimakmur@example.test'),
(N'Komunitas Pati Gowes', N'081200000004', N'Jl. Diponegoro No. 21, Pati', N'patigowes@example.test'),
(N'SMK Harapan Bangsa', N'081200000005', N'Jl. Gajah Mada No. 3, Grobogan', N'smkharapanbangsa@example.test');
GO

-- 2. suppliers
INSERT INTO suppliers (name, phone, address, email)
VALUES
(N'UD Sumber Tekstil', N'081300000001', N'Jl. Kudus-Jepara Km 5, Kudus', N'sumbertekstil@example.test'),
(N'Toko Kancing Makmur', N'081300000002', N'Pasar Kliwon, Kudus', N'kancingmakmur@example.test'),
(N'CV Kain Nusantara', N'081300000003', N'Jl. Industri No. 17, Semarang', N'kainnusantara@example.test'),
(N'UD Aksesoris Jaya', N'081300000004', N'Jl. Pahlawan No. 9, Pati', N'aksesorisjaya@example.test'),
(N'Toko Benang Sentosa', N'081300000005', N'Jl. Veteran No. 30, Demak', N'benangsentosa@example.test');
GO

-- 3. employees
INSERT INTO employees (name, phone, position, active_status)
VALUES
(N'Dewi Lestari', N'081400000001', N'Admin', 1),
(N'Budi Santoso', N'081400000002', N'Staf Gudang', 1),
(N'Rudi Hartono', N'081400000003', N'Tukang Potong', 1),
(N'Siti Aminah', N'081400000004', N'Penjahit', 1),
(N'Agus Prasetyo', N'081400000005', N'Staf QC dan Finishing', 1);
GO

-- 4. materials (saldo awal 0, diperbarui dari stock_movements di akhir script)
INSERT INTO materials (material_name, unit_name, current_stock, minimum_stock)
VALUES
(N'Kain Cotton Combed 30s', N'Kg', 0, 20),
(N'Kain Drill', N'Meter', 0, 50),
(N'Benang Jahit', N'Cone', 0, 10),
(N'Kancing', N'Pcs', 0, 500),
(N'Resleting', N'Pcs', 0, 50),
(N'Tinta Sablon', N'Kg', 0, 2);
GO

-- 5. products
INSERT INTO products (product_name, description)
VALUES
(N'Kaos Oblong', N'Kaos lengan pendek berbahan cotton combed'),
(N'Polo Shirt', N'Kaos berkerah untuk komunitas atau perusahaan'),
(N'Seragam Kerja', N'Kemeja kerja berbahan drill'),
(N'Jaket', N'Jaket komunitas dengan resleting'),
(N'Seragam Sekolah', N'Seragam sekolah atau instansi');
GO

-- 6. product_variants
INSERT INTO product_variants (product_id, size, color, sku)
VALUES
(1, N'M', N'Hitam', N'KO-M-HTM'),
(1, N'L', N'Hitam', N'KO-L-HTM'),
(1, N'L', N'Putih', N'KO-L-PTH'),
(2, N'L', N'Navy', N'PL-L-NVY'),
(2, N'XL', N'Navy', N'PL-XL-NVY'),
(3, N'L', N'Abu-abu', N'SK-L-ABU'),
(3, N'XL', N'Abu-abu', N'SK-XL-ABU'),
(4, N'L', N'Hitam', N'JK-L-HTM');
GO

-- 7. variant_materials (BOM per satu unit)
INSERT INTO variant_materials (variant_id, material_id, quantity_per_unit)
VALUES
(1, 1, 0.25),
(1, 3, 0.01),
(1, 6, 0.02),
(2, 1, 0.28),
(2, 3, 0.01),
(2, 6, 0.02),
(3, 1, 0.28),
(3, 3, 0.01),
(4, 1, 0.35),
(4, 3, 0.015),
(4, 4, 3),
(5, 1, 0.38),
(5, 3, 0.015),
(5, 4, 3),
(6, 2, 1.8),
(6, 3, 0.02),
(6, 4, 6),
(7, 2, 2.0),
(7, 3, 0.02),
(7, 4, 6),
(8, 2, 2.2),
(8, 3, 0.03),
(8, 5, 1);
GO

-- 8. orders (total_amount = jumlah subtotal order_details)
INSERT INTO orders (customer_id, order_date, due_date, status, total_amount)
VALUES
(1, N'2026-10-05', N'2026-10-20', N'Selesai', 3000000),
(2, N'2026-10-08', N'2026-10-25', N'Selesai', 4780000),
(3, N'2026-10-15', N'2026-11-05', N'Diproses', 3750000),
(4, N'2026-10-22', N'2026-11-12', N'Diproses', 3300000),
(5, N'2026-10-28', N'2026-11-20', N'Baru', 3480000);
GO

-- 9. order_details
INSERT INTO order_details (order_id, variant_id, quantity, unit_price, subtotal)
VALUES
(1, 1, 30, 60000, 1800000),
(1, 2, 20, 60000, 1200000),
(2, 4, 40, 95000, 3800000),
(2, 5, 10, 98000, 980000),
(3, 6, 25, 150000, 3750000),
(4, 8, 15, 220000, 3300000),
(5, 3, 60, 58000, 3480000);
GO

-- 10. purchases
INSERT INTO purchases (supplier_id, purchase_date, status, total_amount)
VALUES
(1, N'2026-10-03', N'Diterima', 3580000),
(2, N'2026-10-04', N'Diterima', 650000),
(3, N'2026-10-14', N'Diterima', 4500000),
(4, N'2026-10-20', N'Diterima', 450000),
(5, N'2026-10-30', N'Dipesan', 4300000);
GO

-- 11. purchase_details
INSERT INTO purchase_details (purchase_id, material_id, quantity, unit_price, subtotal)
VALUES
(1, 1, 40, 85000, 3400000),
(1, 3, 15, 12000, 180000),
(2, 4, 1000, 300, 300000),
(2, 6, 5, 70000, 350000),
(3, 2, 100, 45000, 4500000),
(4, 5, 100, 4500, 450000),
(5, 1, 50, 86000, 4300000);
GO

-- 12. production_orders (detail pesanan 1-6; detail 7 belum diproduksi)
INSERT INTO production_orders (order_detail_id, planned_quantity, status, start_date, target_date)
VALUES
(1, 30, N'Selesai', N'2026-10-07', N'2026-10-18'),
(2, 20, N'Selesai', N'2026-10-07', N'2026-10-18'),
(3, 40, N'Selesai', N'2026-10-10', N'2026-10-23'),
(4, 10, N'Selesai', N'2026-10-10', N'2026-10-23'),
(5, 25, N'Berjalan', N'2026-10-17', N'2026-11-03'),
(6, 15, N'Berjalan', N'2026-10-24', N'2026-11-10');
GO

-- 13. production_progress
INSERT INTO production_progress (production_order_id, employee_id, stage_code, status, started_at, completed_at, completed_quantity)
VALUES
(1, 3, N'CUTTING', N'Selesai', N'2026-10-07 08:00:00', N'2026-10-09 16:00:00', 30),
(1, 4, N'SEWING', N'Selesai', N'2026-10-10 08:00:00', N'2026-10-12 16:00:00', 30),
(1, 5, N'FINISHING', N'Selesai', N'2026-10-13 08:00:00', N'2026-10-15 16:00:00', 30),
(1, 5, N'QC', N'Selesai', N'2026-10-16 08:00:00', N'2026-10-18 16:00:00', 30),
(2, 3, N'CUTTING', N'Selesai', N'2026-10-07 08:00:00', N'2026-10-09 16:00:00', 20),
(2, 4, N'SEWING', N'Selesai', N'2026-10-10 08:00:00', N'2026-10-12 16:00:00', 20),
(2, 5, N'FINISHING', N'Selesai', N'2026-10-13 08:00:00', N'2026-10-15 16:00:00', 20),
(2, 5, N'QC', N'Selesai', N'2026-10-16 08:00:00', N'2026-10-18 16:00:00', 20),
(3, 3, N'CUTTING', N'Selesai', N'2026-10-10 08:00:00', N'2026-10-12 16:00:00', 40),
(3, 4, N'SEWING', N'Selesai', N'2026-10-13 08:00:00', N'2026-10-15 16:00:00', 40),
(3, 5, N'FINISHING', N'Selesai', N'2026-10-16 08:00:00', N'2026-10-18 16:00:00', 40),
(3, 5, N'QC', N'Selesai', N'2026-10-19 08:00:00', N'2026-10-21 16:00:00', 40),
(4, 3, N'CUTTING', N'Selesai', N'2026-10-10 08:00:00', N'2026-10-12 16:00:00', 10),
(4, 4, N'SEWING', N'Selesai', N'2026-10-13 08:00:00', N'2026-10-15 16:00:00', 10),
(4, 5, N'FINISHING', N'Selesai', N'2026-10-16 08:00:00', N'2026-10-18 16:00:00', 10),
(4, 5, N'QC', N'Selesai', N'2026-10-19 08:00:00', N'2026-10-21 16:00:00', 10),
(5, 3, N'CUTTING', N'Selesai', N'2026-10-17 08:00:00', N'2026-10-19 16:00:00', 25),
(5, 4, N'SEWING', N'Proses', N'2026-10-20 08:00:00', NULL, 12),
(6, 3, N'CUTTING', N'Selesai', N'2026-10-24 08:00:00', N'2026-10-26 16:00:00', 15);
GO

-- 14. stock_movements (stok masuk dari pembelian, stok keluar dari produksi)
INSERT INTO stock_movements (material_id, movement_type, quantity, movement_date, purchase_detail_id, production_order_id, notes)
VALUES
(1, N'MASUK', 40, N'2026-10-03 10:00:00', 1, NULL, N'Penerimaan pembelian #1'),
(3, N'MASUK', 15, N'2026-10-03 10:00:00', 2, NULL, N'Penerimaan pembelian #1'),
(4, N'MASUK', 1000, N'2026-10-04 10:00:00', 3, NULL, N'Penerimaan pembelian #2'),
(6, N'MASUK', 5, N'2026-10-04 10:00:00', 4, NULL, N'Penerimaan pembelian #2'),
(2, N'MASUK', 100, N'2026-10-14 10:00:00', 5, NULL, N'Penerimaan pembelian #3'),
(5, N'MASUK', 100, N'2026-10-20 10:00:00', 6, NULL, N'Penerimaan pembelian #4'),
(1, N'KELUAR', 7.8, N'2026-10-07 09:00:00', NULL, 1, N'Pemakaian produksi #1'),
(3, N'KELUAR', 0.3, N'2026-10-08 09:00:00', NULL, 1, N'Pemakaian produksi #1'),
(6, N'KELUAR', 0.6, N'2026-10-12 09:00:00', NULL, 1, N'Pemakaian produksi #1'),
(1, N'KELUAR', 5.8, N'2026-10-07 09:30:00', NULL, 2, N'Pemakaian produksi #2'),
(3, N'KELUAR', 0.2, N'2026-10-08 09:30:00', NULL, 2, N'Pemakaian produksi #2'),
(6, N'KELUAR', 0.4, N'2026-10-12 09:30:00', NULL, 2, N'Pemakaian produksi #2'),
(1, N'KELUAR', 14.5, N'2026-10-10 09:00:00', NULL, 3, N'Pemakaian produksi #3'),
(3, N'KELUAR', 0.6, N'2026-10-13 09:00:00', NULL, 3, N'Pemakaian produksi #3'),
(4, N'KELUAR', 120, N'2026-10-13 09:30:00', NULL, 3, N'Pemakaian produksi #3'),
(1, N'KELUAR', 3.9, N'2026-10-10 09:30:00', NULL, 4, N'Pemakaian produksi #4'),
(3, N'KELUAR', 0.15, N'2026-10-13 10:00:00', NULL, 4, N'Pemakaian produksi #4'),
(4, N'KELUAR', 30, N'2026-10-13 10:30:00', NULL, 4, N'Pemakaian produksi #4'),
(2, N'KELUAR', 46, N'2026-10-17 09:00:00', NULL, 5, N'Pemakaian produksi #5'),
(3, N'KELUAR', 0.5, N'2026-10-20 09:00:00', NULL, 5, N'Pemakaian produksi #5'),
(4, N'KELUAR', 150, N'2026-10-20 09:30:00', NULL, 5, N'Pemakaian produksi #5'),
(2, N'KELUAR', 33, N'2026-10-24 09:00:00', NULL, 6, N'Pemakaian produksi #6'),
(5, N'KELUAR', 15, N'2026-10-27 09:00:00', NULL, 6, N'Pemakaian produksi #6');
GO

-- 15. payments
INSERT INTO payments (order_id, payment_date, amount, payment_method, payment_type, notes)
VALUES
(1, N'2026-10-05', 1500000, N'Transfer', N'DP', N'DP 50 persen'),
(1, N'2026-10-20', 1500000, N'Tunai', N'Pelunasan', N'Lunas saat pengambilan'),
(2, N'2026-10-08', 2000000, N'Transfer', N'DP', N'DP awal'),
(2, N'2026-10-15', 1500000, N'QRIS', N'Cicilan', N'Cicilan kedua'),
(2, N'2026-10-25', 1280000, N'Transfer', N'Pelunasan', N'Lunas'),
(3, N'2026-10-15', 1875000, N'Transfer', N'DP', N'DP 50 persen'),
(4, N'2026-10-22', 1000000, N'Tunai', N'DP', N'DP awal'),
(5, N'2026-10-28', 1000000, N'QRIS', N'DP', N'DP awal');
GO

-- Sinkronkan saldo stok materials dengan stock_movements
UPDATE materials
SET current_stock = COALESCE((
    SELECT SUM(CASE WHEN sm.movement_type = N'MASUK' THEN sm.quantity ELSE -sm.quantity END)
    FROM stock_movements sm
    WHERE sm.material_id = materials.material_id), 0);
GO
