-- =====================================================
-- Konveksi Sejahtera - Database KonveksiSejahteraDB
-- DBMS : Microsoft SQL Server
-- File : ddl_konveksi_sejahtera.sql
-- Isi  : 18 tabel beserta PK, FK, UNIQUE, dan CHECK (tanpa data)
-- =====================================================
USE master;
GO
IF DB_ID(N'KonveksiSejahteraDB') IS NULL
    CREATE DATABASE KonveksiSejahteraDB;
GO
USE KonveksiSejahteraDB;
GO

-- ---------- Data master ----------
CREATE TABLE customers (
    customer_id   INT IDENTITY(1,1) NOT NULL,
    name          NVARCHAR(100) NOT NULL,
    phone         NVARCHAR(20)  NULL,
    address       NVARCHAR(250) NULL,
    email         NVARCHAR(100) NULL,
    CONSTRAINT PK_customers PRIMARY KEY (customer_id)
);
GO

CREATE TABLE suppliers (
    supplier_id   INT IDENTITY(1,1) NOT NULL,
    name          NVARCHAR(100) NOT NULL,
    phone         NVARCHAR(20)  NULL,
    address       NVARCHAR(250) NULL,
    email         NVARCHAR(100) NULL,
    CONSTRAINT PK_suppliers PRIMARY KEY (supplier_id)
);
GO

CREATE TABLE employees (
    employee_id   INT IDENTITY(1,1) NOT NULL,
    name          NVARCHAR(100) NOT NULL,
    phone         NVARCHAR(20)  NULL,
    position      NVARCHAR(50)  NOT NULL,
    active_status BIT NOT NULL CONSTRAINT DF_employees_active DEFAULT 1,
    CONSTRAINT PK_employees PRIMARY KEY (employee_id)
);
GO

CREATE TABLE materials (
    material_id   INT IDENTITY(1,1) NOT NULL,
    material_name NVARCHAR(100) NOT NULL,
    unit_name     NVARCHAR(20)  NOT NULL,
    current_stock DECIMAL(12,2) NOT NULL CONSTRAINT DF_materials_stock DEFAULT 0,
    minimum_stock DECIMAL(12,2) NOT NULL CONSTRAINT DF_materials_min DEFAULT 0,
    CONSTRAINT PK_materials PRIMARY KEY (material_id),
    CONSTRAINT UQ_materials_name UNIQUE (material_name),
    CONSTRAINT CK_materials_stock CHECK (current_stock >= 0),
    CONSTRAINT CK_materials_min CHECK (minimum_stock >= 0)
);
GO

CREATE TABLE products (
    product_id    INT IDENTITY(1,1) NOT NULL,
    product_name  NVARCHAR(100) NOT NULL,
    description   NVARCHAR(250) NULL,
    CONSTRAINT PK_products PRIMARY KEY (product_id),
    CONSTRAINT UQ_products_name UNIQUE (product_name)
);
GO

CREATE TABLE product_variants (
    variant_id    INT IDENTITY(1,1) NOT NULL,
    product_id    INT NOT NULL,
    size          NVARCHAR(10) NOT NULL,
    color         NVARCHAR(30) NOT NULL,
    sku           NVARCHAR(30) NOT NULL,
    CONSTRAINT PK_product_variants PRIMARY KEY (variant_id),
    CONSTRAINT UQ_product_variants_sku UNIQUE (sku),
    CONSTRAINT FK_variants_products FOREIGN KEY (product_id) REFERENCES products(product_id)
);
GO

CREATE TABLE variant_materials (
    variant_id        INT NOT NULL,
    material_id       INT NOT NULL,
    quantity_per_unit DECIMAL(10,3) NOT NULL,
    CONSTRAINT PK_variant_materials PRIMARY KEY (variant_id, material_id),
    CONSTRAINT FK_vm_variants  FOREIGN KEY (variant_id)  REFERENCES product_variants(variant_id),
    CONSTRAINT FK_vm_materials FOREIGN KEY (material_id) REFERENCES materials(material_id),
    CONSTRAINT CK_vm_qty CHECK (quantity_per_unit > 0)
);
GO

-- ---------- Transaksi pesanan ----------
CREATE TABLE orders (
    order_id      INT IDENTITY(1,1) NOT NULL,
    customer_id   INT NOT NULL,
    order_date    DATE NOT NULL,
    due_date      DATE NOT NULL,
    status        NVARCHAR(20) NOT NULL CONSTRAINT DF_orders_status DEFAULT N'Baru',
    total_amount  DECIMAL(14,2) NOT NULL CONSTRAINT DF_orders_total DEFAULT 0,
    CONSTRAINT PK_orders PRIMARY KEY (order_id),
    CONSTRAINT FK_orders_customers FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    CONSTRAINT CK_orders_status CHECK (status IN (N'Baru', N'Diproses', N'Selesai', N'Dibatalkan')),
    CONSTRAINT CK_orders_dates  CHECK (due_date >= order_date),
    CONSTRAINT CK_orders_total  CHECK (total_amount >= 0)
);
GO

CREATE TABLE order_details (
    order_detail_id INT IDENTITY(1,1) NOT NULL,
    order_id        INT NOT NULL,
    variant_id      INT NOT NULL,
    quantity        INT NOT NULL,
    unit_price      DECIMAL(12,2) NOT NULL,
    subtotal        DECIMAL(14,2) NOT NULL,
    CONSTRAINT PK_order_details PRIMARY KEY (order_detail_id),
    CONSTRAINT FK_od_orders   FOREIGN KEY (order_id)   REFERENCES orders(order_id),
    CONSTRAINT FK_od_variants FOREIGN KEY (variant_id) REFERENCES product_variants(variant_id),
    CONSTRAINT CK_od_qty   CHECK (quantity > 0),
    CONSTRAINT CK_od_price CHECK (unit_price >= 0),
    CONSTRAINT CK_od_subtotal CHECK (subtotal = quantity * unit_price)
);
GO

-- ---------- Transaksi pembelian ----------
CREATE TABLE purchases (
    purchase_id   INT IDENTITY(1,1) NOT NULL,
    supplier_id   INT NOT NULL,
    purchase_date DATE NOT NULL,
    status        NVARCHAR(20) NOT NULL CONSTRAINT DF_purchases_status DEFAULT N'Dipesan',
    total_amount  DECIMAL(14,2) NOT NULL CONSTRAINT DF_purchases_total DEFAULT 0,
    CONSTRAINT PK_purchases PRIMARY KEY (purchase_id),
    CONSTRAINT FK_purchases_suppliers FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
    CONSTRAINT CK_purchases_status CHECK (status IN (N'Dipesan', N'Diterima', N'Dibatalkan')),
    CONSTRAINT CK_purchases_total  CHECK (total_amount >= 0)
);
GO

CREATE TABLE purchase_details (
    purchase_detail_id INT IDENTITY(1,1) NOT NULL,
    purchase_id        INT NOT NULL,
    material_id        INT NOT NULL,
    quantity           DECIMAL(12,2) NOT NULL,
    unit_price         DECIMAL(12,2) NOT NULL,
    subtotal           DECIMAL(14,2) NOT NULL,
    CONSTRAINT PK_purchase_details PRIMARY KEY (purchase_detail_id),
    CONSTRAINT FK_pd_purchases FOREIGN KEY (purchase_id) REFERENCES purchases(purchase_id),
    CONSTRAINT FK_pd_materials FOREIGN KEY (material_id) REFERENCES materials(material_id),
    CONSTRAINT CK_pd_qty   CHECK (quantity > 0),
    CONSTRAINT CK_pd_price CHECK (unit_price >= 0),
    CONSTRAINT CK_pd_subtotal CHECK (subtotal = quantity * unit_price)
);
GO

-- ---------- Produksi ----------
CREATE TABLE production_orders (
    production_order_id INT IDENTITY(1,1) NOT NULL,
    order_detail_id     INT NOT NULL,
    planned_quantity    INT NOT NULL,
    status              NVARCHAR(20) NOT NULL CONSTRAINT DF_po_status DEFAULT N'Menunggu',
    start_date          DATE NULL,
    target_date         DATE NOT NULL,
    CONSTRAINT PK_production_orders PRIMARY KEY (production_order_id),
    CONSTRAINT FK_po_order_details FOREIGN KEY (order_detail_id) REFERENCES order_details(order_detail_id),
    CONSTRAINT CK_po_qty    CHECK (planned_quantity > 0),
    CONSTRAINT CK_po_status CHECK (status IN (N'Menunggu', N'Berjalan', N'Selesai')),
    CONSTRAINT CK_po_dates  CHECK (start_date IS NULL OR target_date >= start_date)
);
GO

CREATE TABLE production_progress (
    progress_id         INT IDENTITY(1,1) NOT NULL,
    production_order_id INT NOT NULL,
    employee_id         INT NOT NULL,
    stage_code          NVARCHAR(20) NOT NULL,
    status              NVARCHAR(20) NOT NULL CONSTRAINT DF_pp_status DEFAULT N'Proses',
    started_at          DATETIME2 NOT NULL,
    completed_at        DATETIME2 NULL,
    completed_quantity  INT NOT NULL CONSTRAINT DF_pp_qty DEFAULT 0,
    CONSTRAINT PK_production_progress PRIMARY KEY (progress_id),
    CONSTRAINT FK_pp_production_orders FOREIGN KEY (production_order_id) REFERENCES production_orders(production_order_id),
    CONSTRAINT FK_pp_employees FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    CONSTRAINT CK_pp_stage  CHECK (stage_code IN (N'CUTTING', N'SEWING', N'FINISHING', N'QC')),
    CONSTRAINT CK_pp_status CHECK (status IN (N'Proses', N'Selesai')),
    CONSTRAINT CK_pp_qty    CHECK (completed_quantity >= 0),
    CONSTRAINT CK_pp_done   CHECK ((status = N'Selesai' AND completed_at IS NOT NULL AND completed_at >= started_at)
                                   OR (status = N'Proses' AND completed_at IS NULL))
);
GO

-- ---------- Persediaan (stock ledger) ----------
CREATE TABLE stock_movements (
    movement_id         INT IDENTITY(1,1) NOT NULL,
    material_id         INT NOT NULL,
    movement_type       NVARCHAR(10) NOT NULL,
    quantity            DECIMAL(12,2) NOT NULL,
    movement_date       DATETIME2 NOT NULL,
    purchase_detail_id  INT NULL,
    production_order_id INT NULL,
    notes               NVARCHAR(250) NULL,
    CONSTRAINT PK_stock_movements PRIMARY KEY (movement_id),
    CONSTRAINT FK_sm_materials FOREIGN KEY (material_id) REFERENCES materials(material_id),
    CONSTRAINT FK_sm_purchase_details FOREIGN KEY (purchase_detail_id) REFERENCES purchase_details(purchase_detail_id),
    CONSTRAINT FK_sm_production_orders FOREIGN KEY (production_order_id) REFERENCES production_orders(production_order_id),
    CONSTRAINT CK_sm_type CHECK (movement_type IN (N'MASUK', N'KELUAR')),
    CONSTRAINT CK_sm_qty  CHECK (quantity > 0),
    -- tepat satu sumber transaksi yang valid
    CONSTRAINT CK_sm_source CHECK (
        (movement_type = N'MASUK'  AND purchase_detail_id IS NOT NULL AND production_order_id IS NULL) OR
        (movement_type = N'KELUAR' AND production_order_id IS NOT NULL AND purchase_detail_id IS NULL))
);
GO

-- ---------- Pembayaran ----------
CREATE TABLE payments (
    payment_id     INT IDENTITY(1,1) NOT NULL,
    order_id       INT NOT NULL,
    payment_date   DATE NOT NULL,
    amount         DECIMAL(14,2) NOT NULL,
    payment_method NVARCHAR(20) NOT NULL,
    payment_type   NVARCHAR(20) NOT NULL,
    notes          NVARCHAR(250) NULL,
    CONSTRAINT PK_payments PRIMARY KEY (payment_id),
    CONSTRAINT FK_payments_orders FOREIGN KEY (order_id) REFERENCES orders(order_id),
    CONSTRAINT CK_payments_amount CHECK (amount > 0),
    CONSTRAINT CK_payments_method CHECK (payment_method IN (N'Tunai', N'Transfer', N'QRIS')),
    CONSTRAINT CK_payments_type   CHECK (payment_type IN (N'DP', N'Cicilan', N'Pelunasan'))
);
GO

-- ---------- Akses pengguna ----------
CREATE TABLE roles (
    role_id     INT IDENTITY(1,1) NOT NULL,
    role_name   NVARCHAR(30)  NOT NULL,
    description NVARCHAR(150) NULL,
    CONSTRAINT PK_roles PRIMARY KEY (role_id),
    CONSTRAINT UQ_roles_name UNIQUE (role_name)
);
GO

CREATE TABLE users (
    user_id       INT IDENTITY(1,1) NOT NULL,
    employee_id   INT NULL,
    username      NVARCHAR(50)  NOT NULL,
    password_hash NVARCHAR(255) NOT NULL,
    email         NVARCHAR(100) NOT NULL,
    is_active     BIT NOT NULL CONSTRAINT DF_users_active DEFAULT 1,
    CONSTRAINT PK_users PRIMARY KEY (user_id),
    CONSTRAINT UQ_users_username UNIQUE (username),
    CONSTRAINT UQ_users_email    UNIQUE (email),
    CONSTRAINT FK_users_employees FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
);
GO
-- relasi employees 1 : 1 users (employee_id boleh kosong, bila terisi harus unik)
CREATE UNIQUE INDEX UQ_users_employee ON users(employee_id) WHERE employee_id IS NOT NULL;
GO

CREATE TABLE user_roles (
    user_id     INT NOT NULL,
    role_id     INT NOT NULL,
    assigned_at DATETIME2 NOT NULL CONSTRAINT DF_user_roles_assigned DEFAULT SYSDATETIME(),
    CONSTRAINT PK_user_roles PRIMARY KEY (user_id, role_id),
    CONSTRAINT FK_ur_users FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT FK_ur_roles FOREIGN KEY (role_id) REFERENCES roles(role_id)
);
GO
