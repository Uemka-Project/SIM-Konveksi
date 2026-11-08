<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Order;
use App\Models\ProductionOrder;
use App\Models\Purchase;
use App\Support\SimplePdf;
use Illuminate\Support\Collection;

/**
 * Builds the printable FOD documents (FR-O5) as PDF files.
 *
 * The layout is plain ASCII and uses the built-in PDF fonts from SimplePdf,
 * so no extra composer package or document table is required.
 */
class DocumentService
{
    private const COMPANY = 'KONVEKSI SEJAHTERA';

    private const TAGLINE = 'Produksi Kaos & Seragam';

    private const ADDRESS = 'Jl. Ahmad Yani No. 45, Kudus, Jawa Tengah - Telp. 0813-2740-5591';

    private const RULE = '======================================================================================';

    /**
     * @return array{title: string, filename: string, content: string}
     */
    public function render(string $type, int $id): array
    {
        return match ($type) {
            'nota-pesanan' => $this->notaPesanan($id),
            'bukti-bayar' => $this->buktiBayar($id),
            'nota-pembelian' => $this->notaPembelian($id),
            'spk' => $this->spk($id),
            'hasil-produksi' => $this->hasilProduksi($id),
            'surat-jalan' => $this->suratJalan($id),
            default => abort(404, 'Jenis dokumen tidak dikenal.'),
        };
    }

    /**
     * @return array{title: string, filename: string, content: string}
     */
    private function notaPesanan(int $id): array
    {
        $order = Order::query()
            ->with(['customer', 'details.variant.product', 'payments'])
            ->findOrFail($id);

        $total = (float) $order->total_amount;
        $paid = (float) $order->payments->sum('amount');
        $dp = (float) $order->payments->where('payment_type', 'DP')->sum('amount');
        $remaining = max(0, $total - $paid);
        $totalQuantity = (int) $order->details->sum('quantity');
        $notaNumber = $this->slashNumber('KS', $order->order_date, $order->order_id);
        $admin = $this->adminName();

        // Kop sama seperti contoh nota: perusahaan di kiri,
        // judul + nomor di kanan, lalu garis pemisah tebal.
        $lines = $this->letterhead('NOTA PESANAN', $notaNumber);
        $lines[] = '';
        $lines = [...$lines, ...$this->columns(
            [
                'PELANGGAN',
                $this->pair('Nama', (string) ($order->customer?->name ?? '-')),
                $this->pair('Alamat', (string) ($order->customer?->address ?? '-')),
                $this->pair('Telepon', (string) ($order->customer?->phone ?? '-')),
            ],
            [
                'PESANAN',
                $this->pair('Tanggal', $this->date($order->order_date)),
                $this->pair('Tenggat', $this->date($order->due_date)),
                $this->pair('Admin', $admin),
            ],
            48,
        )];
        $lines[] = '';

        $rows = [];

        foreach ($order->details->values() as $index => $detail) {
            $variant = $detail->variant;
            $product = $variant?->product;

            $rows[] = [
                (string) ($index + 1),
                (string) ($product?->product_name ?? '-'),
                (string) ($variant?->size ?? '-'),
                (string) ($variant?->color ?? '-'),
                $this->qty($detail->quantity),
                $this->rupiah($detail->unit_price),
                $this->rupiah($detail->subtotal),
            ];
        }

        $lines = [...$lines, ...$this->table(
            ['NO', 'PRODUK / SPESIFIKASI', 'UKURAN', 'WARNA', 'QTY', 'HARGA', 'SUBTOTAL'],
            $rows,
            [4, 32, 8, 12, 5, 13, 14],
            [5, 6],
            [0, 2, 3, 4],
        )];

        $lines[] = '';
        $lines[] = 'CATATAN';
        $lines[] = '1. Pesanan diproses setelah DP diterima.';
        $lines[] = '2. Pesanan yang sudah masuk produksi tidak dapat dibatalkan.';
        $lines[] = '3. Sisa pembayaran dilunasi sebelum barang diambil.';

        // Blok total di kanan seperti contoh: Total, Uang muka (DP), Sisa.
        $dpLabel = $dp > 0
            ? 'Uang muka (DP '.(int) round($total > 0 ? $dp / $total * 100 : 0).'%)'
            : 'Uang muka (DP)';

        $lines[] = '';
        $lines = [...$lines, ...$this->columns(
            [],
            [
                $this->totalLine('Total pesanan ('.$totalQuantity.' pcs)', $this->rupiah($total)),
                $this->totalLine($dpLabel, $this->rupiah($dp)),
                $this->totalLine('Sisa pembayaran', $this->rupiah($remaining)),
            ],
            38,
        )];

        $lines = [...$lines, ...$this->dualSignature('Pelanggan', 'Admin')];

        return $this->document('nota-pesanan', 'Nota Pesanan', $notaNumber, $lines);
    }

    /**
     * @return array{title: string, filename: string, content: string}
     */
    private function buktiBayar(int $id): array
    {
        $order = Order::query()
            ->with(['customer', 'details', 'payments'])
            ->findOrFail($id);

        $total = (float) $order->total_amount;
        $paid = (float) $order->payments->sum('amount');
        $remaining = max(0, $total - $paid);
        $totalQuantity = (int) $order->details->sum('quantity');
        $lastPayment = $order->payments->sortBy('payment_id')->last();
        // Nomor mengikuti pola contoh: BYK/YYYY/MM/NNN.
        $receiptDate = $lastPayment?->payment_date ?? $order->order_date;
        $number = $this->slashNumber(
            'BYK',
            $receiptDate,
            (int) ($lastPayment?->payment_id ?? $order->order_id),
        );
        $orderNumber = $this->slashNumber('KS', $order->order_date, $order->order_id);
        $admin = $this->adminName();

        // Kop sama seperti nota pesanan; blok kanan menunjukkan
        // nomor pesanan, tanggal cetak, dan admin.
        $lines = $this->letterhead('BUKTI PEMBAYARAN', $number);
        $lines[] = '';
        $lines = [...$lines, ...$this->columns(
            [
                'PELANGGAN',
                $this->pair('Nama', (string) ($order->customer?->name ?? '-')),
                $this->pair('Alamat', (string) ($order->customer?->address ?? '-')),
                $this->pair('Telepon', (string) ($order->customer?->phone ?? '-')),
            ],
            [
                'PEMBAYARAN',
                $this->pair('No. Pesanan', $orderNumber),
                $this->pair('Dicetak', now()->format('d-m-Y H:i')),
                $this->pair('Admin', $admin),
            ],
            48,
        )];
        $lines[] = '';

        $rows = [];

        foreach ($order->payments->sortBy('payment_id')->values() as $index => $payment) {
            $rows[] = [
                (string) ($index + 1),
                $this->date($payment->payment_date),
                (string) $payment->payment_type,
                (string) $payment->payment_method,
                $this->rupiah($payment->amount),
                (string) ($payment->notes ?? '-'),
            ];
        }

        if ($rows === []) {
            $lines[] = '(Belum ada pembayaran yang dicatat.)';
        } else {
            $lines = [...$lines, ...$this->table(
                ['NO', 'TANGGAL', 'JENIS', 'METODE', 'JUMLAH', 'KETERANGAN'],
                $rows,
                [4, 12, 12, 12, 14, 26],
                [4],
                [0, 1, 2, 3],
            )];
        }

        $lines[] = '';
        $lines[] = 'CATATAN';
        $lines[] = '1. Simpan bukti ini sebagai tanda terima pembayaran.';
        $lines[] = '2. Pesanan diproses setelah DP diterima.';

        $lines[] = '';
        $lines = [...$lines, ...$this->columns(
            [],
            [
                $this->totalLine('Total pesanan ('.$totalQuantity.' pcs)', $this->rupiah($total)),
                $this->totalLine('Total dibayar', $this->rupiah($paid)),
                $this->totalLine('Sisa pembayaran', $this->rupiah($remaining)),
            ],
            38,
        )];

        $lines = [...$lines, ...$this->dualSignature('Pelanggan', 'Kasir')];

        return $this->document('bukti-bayar', 'Bukti Pembayaran', $number, $lines);
    }

    /**
     * @return array{title: string, filename: string, content: string}
     */
    private function notaPembelian(int $id): array
    {
        $purchase = Purchase::query()
            ->with(['supplier', 'details.material'])
            ->findOrFail($id);

        $number = $this->slashNumber('PO', $purchase->purchase_date, $purchase->purchase_id);
        $admin = $this->adminName();
        $itemCount = $purchase->details->count();

        // Kop sama seperti nota pesanan; sisi kanan diisi
        // tanggal, status pembelian, dan admin.
        $lines = $this->letterhead('NOTA PEMBELIAN', $number);
        $lines[] = '';
        $lines = [...$lines, ...$this->columns(
            [
                'PEMASOK',
                $this->pair('Nama', (string) ($purchase->supplier?->name ?? '-')),
                $this->pair('Alamat', (string) ($purchase->supplier?->address ?? '-')),
                $this->pair('Telepon', (string) ($purchase->supplier?->phone ?? '-')),
            ],
            [
                'PEMBELIAN',
                $this->pair('Tanggal', $this->date($purchase->purchase_date)),
                $this->pair('Status', (string) $purchase->status),
                $this->pair('Admin', $admin),
            ],
            48,
        )];
        $lines[] = '';

        $rows = [];

        foreach ($purchase->details->values() as $index => $detail) {
            $rows[] = [
                (string) ($index + 1),
                (string) ($detail->material?->material_name ?? '-'),
                (string) ($detail->material?->unit_name ?? '-'),
                $this->qty($detail->quantity),
                $this->rupiah($detail->unit_price),
                $this->rupiah($detail->subtotal),
            ];
        }

        $lines = [...$lines, ...$this->table(
            ['NO', 'BAHAN / SPESIFIKASI', 'SATUAN', 'QTY', 'HARGA', 'SUBTOTAL'],
            $rows,
            [4, 32, 10, 8, 13, 14],
            [4, 5],
            [0, 2, 3],
        )];

        $lines[] = '';
        $lines[] = 'CATATAN';
        $lines[] = '1. Pembelian berstatus Dipesan belum menambah stok.';
        $lines[] = '2. Stok bertambah setelah pembelian ditandai Diterima.';
        $lines[] = '3. Simpan nota ini sebagai bukti pembelian bahan.';

        $lines[] = '';
        $lines = [...$lines, ...$this->columns(
            [],
            [
                $this->totalLine('Total pembelian ('.$itemCount.' item)', $this->rupiah($purchase->total_amount)),
            ],
            38,
        )];

        $lines = [...$lines, ...$this->dualSignature('Pemasok', 'Admin')];

        return $this->document('nota-pembelian', 'Nota Pembelian', $number, $lines);
    }

    /**
     * @return array{title: string, filename: string, content: string}
     */
    private function spk(int $id): array
    {
        $production = $this->loadProduction($id, [
            'orderDetail.order.customer',
            'orderDetail.variant.product',
            'orderDetail.variant.materials',
        ]);

        $detail = $production->orderDetail;
        $order = $detail?->order;
        $variant = $detail?->variant;
        $product = $variant?->product;

        $spkNumber = $this->slashNumber('SPK', $production->start_date ?? now(), $production->production_order_id);
        $orderNumber = $order !== null
            ? $this->slashNumber('KS', $order->order_date, $order->order_id)
            : '-';

        $lines = $this->letterhead('SURAT PERINTAH KERJA (SPK)', $spkNumber);
        $lines[] = '';
        $lines[] = $this->field('Tanggal Terbit', now()->format('d-m-Y'));
        $lines[] = $this->field('No. Pesanan', $orderNumber);
        $lines[] = $this->field('Pelanggan', (string) ($order?->customer?->name ?? '-'));
        $lines[] = $this->field('Status', (string) $production->status);
        $lines[] = $this->field('Tanggal Mulai', $this->date($production->start_date));
        $lines[] = $this->field('Target Selesai', $this->date($production->target_date));
        $lines[] = '';
        $lines[] = 'ITEM PRODUKSI';
        $lines[] = $this->field('Produk', (string) ($product?->product_name ?? '-'));
        $lines[] = $this->field('SKU', (string) ($variant?->sku ?? '-'));
        $lines[] = $this->field('Ukuran / Warna', trim(($variant?->size ?? '-').' / '.($variant?->color ?? '-')));
        $lines[] = $this->field('Qty Pesanan', $this->qty($detail?->quantity));
        $lines[] = $this->field('Qty Rencana', $this->qty($production->planned_quantity));
        $lines[] = '';
        $lines[] = 'KEBUTUHAN BAHAN';

        $rows = [];
        $requirements = $this->requirements($variant?->materials, (int) $production->planned_quantity);

        foreach ($requirements as $index => $requirement) {
            $rows[] = [
                (string) ($index + 1),
                $requirement['name'],
                $requirement['unit'],
                $this->qty($requirement['required']),
                $this->qty($requirement['stock']),
                $this->qty(max(0, $requirement['required'] - $requirement['stock'])),
            ];
        }

        if ($rows === []) {
            $lines[] = '(Varian belum memiliki komponen BOM.)';
        } else {
            $lines = [...$lines, ...$this->table(
                ['NO', 'BAHAN', 'SATUAN', 'KEBUTUHAN', 'STOK', 'KURANG'],
                $rows,
                [4, 30, 10, 12, 12, 12],
                [4, 5, 6],
            )];
        }

        $lines[] = '';
        $lines[] = 'INSTRUKSI KERJA';
        $lines[] = '1. Gudang menyerahkan bahan sesuai daftar kebutuhan di atas.';
        $lines[] = '2. Produksi mengerjakan tahap Cutting, Sewing, Finishing, lalu QC.';
        $lines[] = '3. Catat karyawan, waktu mulai/selesai, dan qty selesai per tahap.';
        $lines[] = '4. Bila QC tidak lolos, kerjakan ulang dan catat progres baru.';
        $lines[] = '';
        $lines[] = 'Diserahkan oleh,                          Diterima oleh,';
        $lines[] = '';
        $lines[] = '';
        $lines[] = '';
        $lines[] = '(_____________________)                   (_____________________)';
        $lines[] = 'Admin Produksi                            Pelaksana';

        return $this->document('spk', 'Surat Perintah Kerja', $spkNumber, $lines);
    }

    /**
     * @return array{title: string, filename: string, content: string}
     */
    private function hasilProduksi(int $id): array
    {
        $production = $this->loadProduction($id, [
            'orderDetail.order.customer',
            'orderDetail.variant.product',
            'progress.employee',
        ]);

        $detail = $production->orderDetail;
        $order = $detail?->order;
        $variant = $detail?->variant;
        $product = $variant?->product;

        $spkNumber = $this->slashNumber('SPK', $production->start_date ?? now(), $production->production_order_id);
        $reportNumber = $this->slashNumber('LHP', $production->start_date ?? now(), $production->production_order_id);
        $orderNumber = $order !== null
            ? $this->slashNumber('KS', $order->order_date, $order->order_id)
            : '-';

        $lines = $this->letterhead('LAPORAN HASIL PRODUKSI DAN QC', $reportNumber);
        $lines[] = '';
        $lines[] = $this->field('No. SPK', $spkNumber);
        $lines[] = $this->field('No. Pesanan', $orderNumber);
        $lines[] = $this->field('Pelanggan', (string) ($order?->customer?->name ?? '-'));
        $lines[] = $this->field('Status Produksi', (string) $production->status);
        $lines[] = '';
        $lines[] = 'ITEM PRODUKSI';
        $lines[] = $this->field('Produk', (string) ($product?->product_name ?? '-'));
        $lines[] = $this->field('SKU', (string) ($variant?->sku ?? '-'));
        $lines[] = $this->field('Ukuran / Warna', trim(($variant?->size ?? '-').' / '.($variant?->color ?? '-')));
        $lines[] = $this->field('Qty Rencana', $this->qty($production->planned_quantity));
        $lines[] = '';
        $lines[] = 'RIWAYAT PROGRES';

        $rows = [];

        foreach ($production->progress->sortBy('progress_id')->values() as $index => $progress) {
            $rows[] = [
                (string) ($index + 1),
                (string) $progress->stage_code,
                (string) ($progress->employee?->name ?? '-'),
                $this->shortDateTime($progress->started_at),
                $this->shortDateTime($progress->completed_at),
                $this->qty($progress->completed_quantity),
                (string) $progress->status,
            ];
        }

        if ($rows === []) {
            $lines[] = '(Belum ada progres yang dicatat.)';
        } else {
            $lines = [...$lines, ...$this->table(
                ['NO', 'TAHAP', 'KARYAWAN', 'MULAI', 'SELESAI', 'QTY', 'STATUS'],
                $rows,
                [3, 11, 20, 15, 15, 5, 10],
                [3, 5, 6],
            )];
        }

        $completedStages = $production->progress
            ->where('status', 'Selesai')
            ->pluck('stage_code')
            ->unique();
        $qcQuantity = (float) $production->progress
            ->where('stage_code', 'QC')
            ->where('status', 'Selesai')
            ->sum('completed_quantity');

        $lines[] = '';
        $lines[] = $this->field('Tahap Selesai', $completedStages->count().' dari 4 tahap');
        $lines[] = $this->field('Qty Lulus QC', $this->qty($qcQuantity));
        $lines[] = $this->field('Kesimpulan', $production->status === 'Selesai'
            ? 'Produksi selesai dan siap diserahkan.'
            : 'Produksi masih berjalan.');
        $lines[] = '';
        $lines[] = 'Catatan QC:';
        $lines[] = '- Pemeriksaan meliputi jahitan, ukuran, kebersihan, dan kerapian.';
        $lines[] = '- Pengerjaan ulang dicatat sebagai baris progres baru pada tahap terkait.';
        $lines = [...$lines, ...$this->signature('Diperiksa oleh,', 'QC')];

        return $this->document('hasil-produksi', 'Laporan Hasil Produksi dan QC', $reportNumber, $lines);
    }

    /**
     * @return array{title: string, filename: string, content: string}
     */
    private function suratJalan(int $id): array
    {
        $order = Order::query()
            ->with(['customer', 'details.variant.product'])
            ->findOrFail($id);

        $jalanNumber = $this->slashNumber('SJ', $order->order_date, $order->order_id);
        $orderNumber = $this->slashNumber('KS', $order->order_date, $order->order_id);

        $lines = $this->letterhead('SURAT JALAN DAN TANDA TERIMA', $jalanNumber);
        $lines[] = '';
        $lines[] = $this->field('Tanggal', now()->format('d-m-Y'));
        $lines[] = $this->field('No. Pesanan', $orderNumber);
        $lines[] = $this->field('Status Pesanan', (string) $order->status);
        $lines[] = '';
        $lines[] = 'TUJUAN PENGIRIMAN';
        $lines[] = $this->field('Nama', (string) ($order->customer?->name ?? '-'));
        $lines[] = $this->field('Telepon', (string) ($order->customer?->phone ?? '-'));
        $lines[] = $this->field('Alamat', (string) ($order->customer?->address ?? '-'));
        $lines[] = '';
        $lines[] = 'BARANG YANG DISERAHKAN';

        $rows = [];
        $totalQuantity = 0;

        foreach ($order->details->values() as $index => $detail) {
            $variant = $detail->variant;

            $totalQuantity += (int) $detail->quantity;

            $rows[] = [
                (string) ($index + 1),
                (string) ($variant?->product?->product_name ?? '-'),
                (string) ($variant?->size ?? '-'),
                (string) ($variant?->color ?? '-'),
                $this->qty($detail->quantity),
            ];
        }

        $lines = [...$lines, ...$this->table(
            ['NO', 'PRODUK', 'UKURAN', 'WARNA', 'QTY'],
            $rows,
            [4, 36, 10, 16, 8],
            [4],
        )];

        $lines[] = '';
        $lines[] = $this->field('Total Barang', $this->qty($totalQuantity).' pcs');
        $lines[] = '';
        $lines[] = 'Barang di atas telah diserahkan dan diterima dalam keadaan baik serta';
        $lines[] = 'lengkap sesuai pesanan pelanggan.';
        $lines[] = '';
        $lines[] = 'Diserahkan oleh,                          Diterima oleh,';
        $lines[] = '';
        $lines[] = '';
        $lines[] = '';
        $lines[] = '(_____________________)                   (_____________________)';
        $lines[] = 'Admin                                     Pelanggan';

        return $this->document('surat-jalan', 'Surat Jalan dan Tanda Terima', $jalanNumber, $lines);
    }

    /**
     * @return array{title: string, filename: string, content: string}
     */
    private function document(string $type, string $title, string $number, array $lines): array
    {
        $lines[] = '';
        $lines[] = 'Dicetak pada '.now()->format('d-m-Y H:i').' oleh '.$this->printedBy().'.';

        return [
            'title' => $title,
            'filename' => $type.'-'.str_replace('/', '-', $number).'.pdf',
            'content' => SimplePdf::render($title.' - '.self::COMPANY, $lines),
        ];
    }

    /**
     * @param  list<string>  $relations
     */
    private function loadProduction(int $id, array $relations): ProductionOrder
    {
        return ProductionOrder::query()
            ->with($relations)
            ->findOrFail($id);
    }

    /**
     * Kop perusahaan seperti pada contoh nota: perusahaan di kiri,
     * judul + nomor di kanan, lalu garis pemisah.
     *
     * @return list<string>
     */
    private function letterhead(string $title, string $number): array
    {
        return [
            self::COMPANY,
            self::TAGLINE,
            self::ADDRESS,
            str_pad($title, 86, ' ', STR_PAD_LEFT),
            str_pad('No. '.$number, 86, ' ', STR_PAD_LEFT),
            self::RULE,
        ];
    }

    /**
     * @return list<string>
     */
    private function headerLines(bool $withAddress = false): array
    {
        $lines = [
            self::COMPANY,
            self::TAGLINE,
            self::ADDRESS,
        ];

        $lines[] = self::RULE;

        return $lines;
    }

    /**
     * @param  Collection<int, Material>|null  $materials
     * @return list<array{name: string, unit: string, required: float, stock: float}>
     */
    private function requirements(?Collection $materials, int $plannedQuantity): array
    {
        if ($materials === null) {
            return [];
        }

        $requirements = [];

        foreach ($materials as $material) {
            $required = round($plannedQuantity * (float) $material->pivot->quantity_per_unit, 2);

            if ($required <= 0) {
                continue;
            }

            $requirements[] = [
                'name' => (string) $material->material_name,
                'unit' => (string) $material->unit_name,
                'required' => $required,
                'stock' => (float) $material->current_stock,
            ];
        }

        usort(
            $requirements,
            fn (array $left, array $right): int => strcmp($left['name'], $right['name']),
        );

        return $requirements;
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     * @return list<string>
     */
    private function columns(array $left, array $right, int $leftWidth = 52): array
    {
        $count = max(count($left), count($right));
        $lines = [];

        for ($index = 0; $index < $count; $index++) {
            $cell = mb_substr((string) ($left[$index] ?? ''), 0, $leftWidth);

            $lines[] = str_pad($cell, $leftWidth).(string) ($right[$index] ?? '');
        }

        return $lines;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  list<int>  $widths
     * @param  list<int>  $rightAligned
     * @param  list<int>  $centered
     * @return list<string>
     */
    private function table(array $headers, array $rows, array $widths, array $rightAligned = [], array $centered = []): array
    {
        $lines = [
            $this->row($headers, $widths, $rightAligned, $centered),
            $this->divider($widths),
        ];

        foreach ($rows as $row) {
            $lines[] = $this->row($row, $widths, $rightAligned, $centered);
        }

        return $lines;
    }

    /**
     * @param  list<string>  $cells
     * @param  list<int>  $widths
     * @param  list<int>  $rightAligned
     * @param  list<int>  $centered
     */
    private function row(array $cells, array $widths, array $rightAligned = [], array $centered = []): string
    {
        $parts = [];

        foreach ($widths as $index => $width) {
            $value = mb_substr((string) ($cells[$index] ?? ''), 0, $width);

            if (in_array($index, $centered, true)) {
                $parts[] = str_pad($value, $width, ' ', STR_PAD_BOTH);
            } elseif (in_array($index, $rightAligned, true)) {
                $parts[] = str_pad($value, $width, ' ', STR_PAD_LEFT);
            } else {
                $parts[] = str_pad($value, $width);
            }
        }

        return rtrim(implode(' ', $parts));
    }

    /**
     * @param  list<int>  $widths
     */
    private function divider(array $widths): string
    {
        return implode(' ', array_map(
            fn (int $width): string => str_repeat('-', $width),
            $widths,
        ));
    }

    /**
     * @return list<string>
     */
    private function signature(string $label, string $role): array
    {
        return [
            '',
            $label,
            '',
            '',
            '',
            '(_____________________)',
            $role,
        ];
    }

    private function field(string $label, string $value, int $labelWidth = 18): string
    {
        return str_pad($label, $labelWidth).': '.$value;
    }

    /**
     * Pasangan label-nilai kecil seperti contoh nota (tanpa tanda titik dua).
     */
    private function pair(string $label, string $value, int $labelWidth = 12): string
    {
        return str_pad($label, $labelWidth).$value;
    }

    /**
     * Baris ringkasan total: label di kiri, jumlah rata kanan,
     * tanpa titik dua seperti pada contoh nota.
     */
    private function totalLine(string $label, string $amount, int $labelWidth = 24, int $amountWidth = 16): string
    {
        return str_pad($label, $labelWidth).str_pad($amount, $amountWidth, ' ', STR_PAD_LEFT);
    }

    private function number(string $prefix, int $id): string
    {
        return $prefix.'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Nomor dokumen bergaya contoh nota, misalnya KS/2026/11/001.
     * Tahun + bulan diambil dari tanggal dokumen; urutan tiga digit
     * mengikuti ID baris data.
     */
    private function slashNumber(string $prefix, mixed $date, int $id): string
    {
        $date = $date ?? now();
        $yearMonth = $date instanceof \DateTimeInterface ? $date->format('Y/m') : now()->format('Y/m');

        return $prefix.'/'.$yearMonth.'/'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Nama admin yang mencetak dokumen (nama karyawan bila tertaut,
     * jatuh ke username agar tidak kosong di luar sesi login).
     */
    private function adminName(): string
    {
        $user = auth()->user();

        if ($user === null) {
            return 'Admin';
        }

        $name = $user->employee?->name ?? $user->username ?? 'Admin';

        return (string) $name;
    }

    /**
     * Dua blok tanda tangan berdampingan seperti bagian bawah contoh nota
     * (Pelanggan di kiri, Admin di kanan). Baris digambar dengan garis
     * bawah dan nama penandatangan ditulis terpusat di bawahnya.
     *
     * @return list<string>
     */
    private function dualSignature(string $leftLabel, string $rightLabel): array
    {
        $line = '(_____________________)';
        $leftName = str_pad($leftLabel, 23, ' ', STR_PAD_BOTH);
        $rightName = str_pad($rightLabel, 23, ' ', STR_PAD_BOTH);

        return [
            '',
            '',
            str_pad($line, 43).$line,
            str_pad($leftName, 43).$rightName,
        ];
    }

    private function date(mixed $value): string
    {
        return $value === null ? '-' : $value->format('d-m-Y');
    }

    private function shortDateTime(mixed $value): string
    {
        return $value === null ? '-' : $value->format('d-m H:i');
    }

    private function rupiah(float|int|string|null $value): string
    {
        return 'Rp '.$this->amount($value);
    }

    private function amount(float|int|string|null $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function qty(float|int|string|null $value): string
    {
        $formatted = rtrim(number_format((float) $value, 2, ',', '.'), '0');

        return rtrim($formatted, ',');
    }

    private function printedBy(): string
    {
        return (string) (auth()->user()?->username ?? 'sistem');
    }
}
