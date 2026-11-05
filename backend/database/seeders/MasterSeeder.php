<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class MasterSeeder extends Seeder
{
    /**
     * Seed the master data from docs/02_dml_contoh.sql.
     */
    public function run(): void
    {
        $customers = [
            ['name' => 'Toko Berkah Sandang', 'phone' => '081200000001', 'address' => 'Jl. Sunan Muria No. 12, Kudus', 'email' => 'berkahsandang@example.test'],
            ['name' => 'CV Karya Mandiri', 'phone' => '081200000002', 'address' => 'Jl. Pemuda No. 45, Jepara', 'email' => 'karyamandiri@example.test'],
            ['name' => 'Koperasi Tani Makmur', 'phone' => '081200000003', 'address' => 'Jl. Sultan Fatah No. 8, Demak', 'email' => 'tanimakmur@example.test'],
            ['name' => 'Komunitas Pati Gowes', 'phone' => '081200000004', 'address' => 'Jl. Diponegoro No. 21, Pati', 'email' => 'patigowes@example.test'],
            ['name' => 'SMK Harapan Bangsa', 'phone' => '081200000005', 'address' => 'Jl. Gajah Mada No. 3, Grobogan', 'email' => 'smkharapanbangsa@example.test'],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(['name' => $customer['name']], $customer);
        }

        $suppliers = [
            ['name' => 'UD Sumber Tekstil', 'phone' => '081300000001', 'address' => 'Jl. Kudus-Jepara Km 5, Kudus', 'email' => 'sumbertekstil@example.test'],
            ['name' => 'Toko Kancing Makmur', 'phone' => '081300000002', 'address' => 'Pasar Kliwon, Kudus', 'email' => 'kancingmakmur@example.test'],
            ['name' => 'CV Kain Nusantara', 'phone' => '081300000003', 'address' => 'Jl. Industri No. 17, Semarang', 'email' => 'kainnusantara@example.test'],
            ['name' => 'UD Aksesoris Jaya', 'phone' => '081300000004', 'address' => 'Jl. Pahlawan No. 9, Pati', 'email' => 'aksesorisjaya@example.test'],
            ['name' => 'Toko Benang Sentosa', 'phone' => '081300000005', 'address' => 'Jl. Veteran No. 30, Demak', 'email' => 'benangsentosa@example.test'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(['name' => $supplier['name']], $supplier);
        }

        $employees = [
            ['name' => 'Dewi Lestari', 'phone' => '081400000001', 'position' => 'Admin', 'active_status' => true],
            ['name' => 'Budi Santoso', 'phone' => '081400000002', 'position' => 'Staf Gudang', 'active_status' => true],
            ['name' => 'Rudi Hartono', 'phone' => '081400000003', 'position' => 'Tukang Potong', 'active_status' => true],
            ['name' => 'Siti Aminah', 'phone' => '081400000004', 'position' => 'Penjahit', 'active_status' => true],
            ['name' => 'Agus Prasetyo', 'phone' => '081400000005', 'position' => 'Staf QC dan Finishing', 'active_status' => true],
        ];

        foreach ($employees as $employee) {
            Employee::updateOrCreate(['name' => $employee['name']], $employee);
        }

        $materials = [
            ['material_name' => 'Kain Cotton Combed 30s', 'unit_name' => 'Kg', 'minimum_stock' => 20],
            ['material_name' => 'Kain Drill', 'unit_name' => 'Meter', 'minimum_stock' => 50],
            ['material_name' => 'Benang Jahit', 'unit_name' => 'Cone', 'minimum_stock' => 10],
            ['material_name' => 'Kancing', 'unit_name' => 'Pcs', 'minimum_stock' => 500],
            ['material_name' => 'Resleting', 'unit_name' => 'Pcs', 'minimum_stock' => 50],
            ['material_name' => 'Tinta Sablon', 'unit_name' => 'Kg', 'minimum_stock' => 2],
        ];

        foreach ($materials as $material) {
            Material::updateOrCreate(
                ['material_name' => $material['material_name']],
                [...$material, 'current_stock' => 0],
            );
        }

        $products = [
            ['product_name' => 'Kaos Oblong', 'description' => 'Kaos lengan pendek berbahan cotton combed'],
            ['product_name' => 'Polo Shirt', 'description' => 'Kaos berkerah untuk komunitas atau perusahaan'],
            ['product_name' => 'Seragam Kerja', 'description' => 'Kemeja kerja berbahan drill'],
            ['product_name' => 'Jaket', 'description' => 'Jaket komunitas dengan resleting'],
            ['product_name' => 'Seragam Sekolah', 'description' => 'Seragam sekolah atau instansi'],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['product_name' => $product['product_name']], $product);
        }

        $variants = [
            ['product_name' => 'Kaos Oblong', 'size' => 'M', 'color' => 'Hitam', 'sku' => 'KO-M-HTM'],
            ['product_name' => 'Kaos Oblong', 'size' => 'L', 'color' => 'Hitam', 'sku' => 'KO-L-HTM'],
            ['product_name' => 'Kaos Oblong', 'size' => 'L', 'color' => 'Putih', 'sku' => 'KO-L-PTH'],
            ['product_name' => 'Polo Shirt', 'size' => 'L', 'color' => 'Navy', 'sku' => 'PL-L-NVY'],
            ['product_name' => 'Polo Shirt', 'size' => 'XL', 'color' => 'Navy', 'sku' => 'PL-XL-NVY'],
            ['product_name' => 'Seragam Kerja', 'size' => 'L', 'color' => 'Abu-abu', 'sku' => 'SK-L-ABU'],
            ['product_name' => 'Seragam Kerja', 'size' => 'XL', 'color' => 'Abu-abu', 'sku' => 'SK-XL-ABU'],
            ['product_name' => 'Jaket', 'size' => 'L', 'color' => 'Hitam', 'sku' => 'JK-L-HTM'],
        ];

        foreach ($variants as $variant) {
            $product = Product::where('product_name', $variant['product_name'])->firstOrFail();

            ProductVariant::updateOrCreate(
                ['sku' => $variant['sku']],
                [
                    'product_id' => $product->product_id,
                    'size' => $variant['size'],
                    'color' => $variant['color'],
                ],
            );
        }

        $bom = [
            ['sku' => 'KO-M-HTM', 'material' => 'Kain Cotton Combed 30s', 'quantity' => 0.25],
            ['sku' => 'KO-M-HTM', 'material' => 'Benang Jahit', 'quantity' => 0.01],
            ['sku' => 'KO-M-HTM', 'material' => 'Tinta Sablon', 'quantity' => 0.02],
            ['sku' => 'KO-L-HTM', 'material' => 'Kain Cotton Combed 30s', 'quantity' => 0.28],
            ['sku' => 'KO-L-HTM', 'material' => 'Benang Jahit', 'quantity' => 0.01],
            ['sku' => 'KO-L-HTM', 'material' => 'Tinta Sablon', 'quantity' => 0.02],
            ['sku' => 'KO-L-PTH', 'material' => 'Kain Cotton Combed 30s', 'quantity' => 0.28],
            ['sku' => 'KO-L-PTH', 'material' => 'Benang Jahit', 'quantity' => 0.01],
            ['sku' => 'PL-L-NVY', 'material' => 'Kain Cotton Combed 30s', 'quantity' => 0.35],
            ['sku' => 'PL-L-NVY', 'material' => 'Benang Jahit', 'quantity' => 0.015],
            ['sku' => 'PL-L-NVY', 'material' => 'Kancing', 'quantity' => 3],
            ['sku' => 'PL-XL-NVY', 'material' => 'Kain Cotton Combed 30s', 'quantity' => 0.38],
            ['sku' => 'PL-XL-NVY', 'material' => 'Benang Jahit', 'quantity' => 0.015],
            ['sku' => 'PL-XL-NVY', 'material' => 'Kancing', 'quantity' => 3],
            ['sku' => 'SK-L-ABU', 'material' => 'Kain Drill', 'quantity' => 1.8],
            ['sku' => 'SK-L-ABU', 'material' => 'Benang Jahit', 'quantity' => 0.02],
            ['sku' => 'SK-L-ABU', 'material' => 'Kancing', 'quantity' => 6],
            ['sku' => 'SK-XL-ABU', 'material' => 'Kain Drill', 'quantity' => 2.0],
            ['sku' => 'SK-XL-ABU', 'material' => 'Benang Jahit', 'quantity' => 0.02],
            ['sku' => 'SK-XL-ABU', 'material' => 'Kancing', 'quantity' => 6],
            ['sku' => 'JK-L-HTM', 'material' => 'Kain Drill', 'quantity' => 2.2],
            ['sku' => 'JK-L-HTM', 'material' => 'Benang Jahit', 'quantity' => 0.03],
            ['sku' => 'JK-L-HTM', 'material' => 'Resleting', 'quantity' => 1],
        ];

        foreach ($bom as $item) {
            $variant = ProductVariant::where('sku', $item['sku'])->firstOrFail();
            $material = Material::where('material_name', $item['material'])->firstOrFail();

            $variant->materials()->syncWithoutDetaching([
                $material->material_id => ['quantity_per_unit' => $item['quantity']],
            ]);
        }
    }
}
