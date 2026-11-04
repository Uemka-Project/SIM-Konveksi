-- =====================================================
-- File : 04_verifikasi.sql (query uji dan bukti untuk laporan)
-- =====================================================
USE KonveksiSejahteraDB;
GO

-- 1. Isi seluruh tabel (ambil screenshot per tabel)
SELECT * FROM customers;
SELECT * FROM suppliers;
SELECT * FROM employees;
SELECT * FROM materials;
SELECT * FROM products;
SELECT * FROM product_variants;
SELECT * FROM variant_materials;
SELECT * FROM orders;
SELECT * FROM order_details;
SELECT * FROM purchases;
SELECT * FROM purchase_details;
SELECT * FROM stock_movements;
SELECT * FROM production_orders;
SELECT * FROM production_progress;
SELECT * FROM payments;
SELECT * FROM roles;
SELECT user_id, employee_id, username, email, is_active FROM users;  -- password_hash sengaja tidak ditampilkan
SELECT * FROM user_roles;

-- 2. Jumlah record per tabel
SELECT 'customers' AS tabel, COUNT(*) AS jumlah FROM customers UNION ALL
SELECT 'suppliers', COUNT(*) FROM suppliers UNION ALL
SELECT 'employees', COUNT(*) FROM employees UNION ALL
SELECT 'materials', COUNT(*) FROM materials UNION ALL
SELECT 'products', COUNT(*) FROM products UNION ALL
SELECT 'product_variants', COUNT(*) FROM product_variants UNION ALL
SELECT 'variant_materials', COUNT(*) FROM variant_materials UNION ALL
SELECT 'orders', COUNT(*) FROM orders UNION ALL
SELECT 'order_details', COUNT(*) FROM order_details UNION ALL
SELECT 'purchases', COUNT(*) FROM purchases UNION ALL
SELECT 'purchase_details', COUNT(*) FROM purchase_details UNION ALL
SELECT 'stock_movements', COUNT(*) FROM stock_movements UNION ALL
SELECT 'production_orders', COUNT(*) FROM production_orders UNION ALL
SELECT 'production_progress', COUNT(*) FROM production_progress UNION ALL
SELECT 'payments', COUNT(*) FROM payments UNION ALL
SELECT 'roles', COUNT(*) FROM roles UNION ALL
SELECT 'users', COUNT(*) FROM users UNION ALL
SELECT 'user_roles', COUNT(*) FROM user_roles;

-- 3. Estimasi kebutuhan bahan per pesanan (BOM x kuantitas)
SELECT o.order_id, m.material_name, m.unit_name,
       SUM(vm.quantity_per_unit * od.quantity) AS kebutuhan_estimasi
FROM orders o
JOIN order_details od ON od.order_id = o.order_id
JOIN variant_materials vm ON vm.variant_id = od.variant_id
JOIN materials m ON m.material_id = vm.material_id
GROUP BY o.order_id, m.material_name, m.unit_name
ORDER BY o.order_id, m.material_name;

-- 4. Bahan dengan stok di bawah batas minimum
SELECT material_name, unit_name, current_stock, minimum_stock
FROM materials
WHERE current_stock < minimum_stock;

-- 5. Rekonsiliasi saldo stok (selisih harus 0)
SELECT m.material_name, m.current_stock AS saldo_tabel,
       COALESCE(SUM(CASE WHEN sm.movement_type = N'MASUK' THEN sm.quantity ELSE -sm.quantity END), 0) AS saldo_ledger
FROM materials m
LEFT JOIN stock_movements sm ON sm.material_id = m.material_id
GROUP BY m.material_name, m.current_stock;

-- 6. Sisa pembayaran per pesanan
SELECT o.order_id, c.name AS pelanggan, o.total_amount,
       COALESCE(SUM(p.amount), 0) AS sudah_dibayar,
       o.total_amount - COALESCE(SUM(p.amount), 0) AS sisa_tagihan
FROM orders o
JOIN customers c ON c.customer_id = o.customer_id
LEFT JOIN payments p ON p.order_id = o.order_id
GROUP BY o.order_id, c.name, o.total_amount
ORDER BY o.order_id;

-- 7. Progres produksi per perintah produksi
SELECT po.production_order_id, pr.product_name, v.size, v.color, po.status,
       pp.stage_code, pp.status AS status_tahap, e.name AS petugas, pp.completed_quantity
FROM production_orders po
JOIN order_details od ON od.order_detail_id = po.order_detail_id
JOIN product_variants v ON v.variant_id = od.variant_id
JOIN products pr ON pr.product_id = v.product_id
JOIN production_progress pp ON pp.production_order_id = po.production_order_id
JOIN employees e ON e.employee_id = pp.employee_id
ORDER BY po.production_order_id, pp.progress_id;

-- 8. Validasi total pesanan = jumlah subtotal detail (hasil harus kosong)
SELECT o.order_id, o.total_amount, SUM(od.subtotal) AS jumlah_subtotal
FROM orders o JOIN order_details od ON od.order_id = o.order_id
GROUP BY o.order_id, o.total_amount
HAVING o.total_amount <> SUM(od.subtotal);

-- 9. Pengguna beserta peran dan karyawan terkait
SELECT u.username, u.is_active, e.name AS karyawan, r.role_name
FROM users u
LEFT JOIN employees e ON e.employee_id = u.employee_id
JOIN user_roles ur ON ur.user_id = u.user_id
JOIN roles r ON r.role_id = ur.role_id
ORDER BY u.username;

-- 10. Total rencana produksi per item pesanan tidak boleh melebihi qty pesanan
--     (relasi order_details 1:M production_orders; hasil harus kosong)
SELECT od.order_detail_id, od.quantity AS qty_pesanan, SUM(po.planned_quantity) AS total_rencana
FROM order_details od
JOIN production_orders po ON po.order_detail_id = od.order_detail_id
GROUP BY od.order_detail_id, od.quantity
HAVING SUM(po.planned_quantity) > od.quantity;

-- 11. Perintah produksi Selesai harus punya 4 tahap Selesai (hasil harus kosong)
SELECT po.production_order_id
FROM production_orders po
LEFT JOIN production_progress pp ON pp.production_order_id = po.production_order_id AND pp.status = N'Selesai'
WHERE po.status = N'Selesai'
GROUP BY po.production_order_id
HAVING COUNT(DISTINCT pp.stage_code) <> 4;

-- 12. Uji constraint: perintah berikut HARUS ditolak SQL Server
-- (stok keluar tanpa sumber produksi)
-- INSERT INTO stock_movements (material_id, movement_type, quantity, movement_date)
-- VALUES (1, N'KELUAR', 5, '2026-10-31 10:00:00');
