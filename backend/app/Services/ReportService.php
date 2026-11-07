<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Order;
use App\Models\ProductionProgress;
use App\Models\Purchase;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * @var list<string>
     */
    private const STAGES = ['CUTTING', 'SEWING', 'FINISHING', 'QC'];

    /**
     * @var array<string, string>
     */
    private const STAGE_LABELS = [
        'CUTTING' => 'Cutting',
        'SEWING' => 'Sewing',
        'FINISHING' => 'Finishing',
        'QC' => 'QC',
    ];

    /**
     * Sales report grouped per order for the selected period (FR-R2).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function sales(array $filters): array
    {
        $orders = Order::query()
            ->with('customer')
            ->withSum('payments', 'amount')
            ->when(! empty($filters['date_from']), function ($query) use ($filters): void {
                $query->whereDate('order_date', '>=', $filters['date_from']);
            })
            ->when(! empty($filters['date_to']), function ($query) use ($filters): void {
                $query->whereDate('order_date', '<=', $filters['date_to']);
            })
            ->when(! empty($filters['status']), function ($query) use ($filters): void {
                $query->where('status', $filters['status']);
            })
            ->orderBy('order_date')
            ->orderBy('order_id')
            ->get();

        $rows = $orders->map(function (Order $order): array {
            $total = (float) $order->total_amount;
            $paid = (float) ($order->payments_sum_amount ?? 0);

            return [
                'order_id' => $order->order_id,
                'order_date' => $order->order_date?->format('Y-m-d'),
                'due_date' => $order->due_date?->format('Y-m-d'),
                'customer' => $order->customer?->name,
                'status' => $order->status,
                'total_amount' => round($total, 2),
                'paid_amount' => round($paid, 2),
                'remaining_amount' => max(0, round($total - $paid, 2)),
            ];
        })->values()->all();

        $effective = $orders->filter(fn (Order $order): bool => $order->status !== 'Dibatalkan');
        $totalSales = (float) $effective->sum(fn (Order $order): float => (float) $order->total_amount);
        $paidAmount = (float) $effective->sum(
            fn (Order $order): float => (float) ($order->payments_sum_amount ?? 0),
        );

        return [
            'type' => 'sales',
            'period' => $this->period($filters),
            'summary' => [
                'order_count' => $orders->count(),
                'total_sales' => round($totalSales, 2),
                'paid_amount' => round($paidAmount, 2),
                'remaining_amount' => max(0, round($totalSales - $paidAmount, 2)),
                'completed_orders' => $orders->where('status', 'Selesai')->count(),
                'cancelled_orders' => $orders->where('status', 'Dibatalkan')->count(),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Purchases report grouped per purchase for the selected period (FR-R2).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function purchases(array $filters): array
    {
        $purchases = Purchase::query()
            ->with('supplier')
            ->withCount('details')
            ->when(! empty($filters['date_from']), function ($query) use ($filters): void {
                $query->whereDate('purchase_date', '>=', $filters['date_from']);
            })
            ->when(! empty($filters['date_to']), function ($query) use ($filters): void {
                $query->whereDate('purchase_date', '<=', $filters['date_to']);
            })
            ->when(! empty($filters['status']), function ($query) use ($filters): void {
                $query->where('status', $filters['status']);
            })
            ->orderBy('purchase_date')
            ->orderBy('purchase_id')
            ->get();

        $rows = $purchases->map(fn (Purchase $purchase): array => [
            'purchase_id' => $purchase->purchase_id,
            'purchase_date' => $purchase->purchase_date?->format('Y-m-d'),
            'supplier' => $purchase->supplier?->name,
            'status' => $purchase->status,
            'item_count' => (int) ($purchase->details_count ?? 0),
            'total_amount' => (float) $purchase->total_amount,
        ])->values()->all();

        $effective = $purchases->filter(fn (Purchase $purchase): bool => $purchase->status !== 'Dibatalkan');
        $totalPurchases = (float) $effective->sum(
            fn (Purchase $purchase): float => (float) $purchase->total_amount,
        );

        return [
            'type' => 'purchases',
            'period' => $this->period($filters),
            'summary' => [
                'purchase_count' => $purchases->count(),
                'total_purchases' => round($totalPurchases, 2),
                'received_total' => round((float) $purchases
                    ->where('status', 'Diterima')
                    ->sum(fn (Purchase $purchase): float => (float) $purchase->total_amount), 2),
                'ordered_total' => round((float) $purchases
                    ->where('status', 'Dipesan')
                    ->sum(fn (Purchase $purchase): float => (float) $purchase->total_amount), 2),
                'cancelled_purchases' => $purchases->where('status', 'Dibatalkan')->count(),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Stock card report with a running balance when a material is selected.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function stockCard(array $filters): array
    {
        $material = ! empty($filters['material_id'])
            ? Material::query()->findOrFail((int) $filters['material_id'])
            : null;

        // Load every movement in the period so a type filter does not corrupt
        // the running balance of a selected material.
        $periodMovements = StockMovement::query()
            ->with(['material', 'purchaseDetail.purchase'])
            ->when($material !== null, function ($query) use ($material): void {
                $query->where('material_id', $material->material_id);
            })
            ->when(! empty($filters['date_from']), function ($query) use ($filters): void {
                $query->whereDate('movement_date', '>=', $filters['date_from']);
            })
            ->when(! empty($filters['date_to']), function ($query) use ($filters): void {
                $query->whereDate('movement_date', '<=', $filters['date_to']);
            })
            ->orderBy('movement_date')
            ->orderBy('movement_id')
            ->get();

        $openingStock = null;
        $balance = null;
        $balanceByMovement = [];

        if ($material !== null) {
            $allMovements = StockMovement::query()
                ->where('material_id', $material->material_id)
                ->get();

            $initialStock = (float) $material->current_stock - $this->netMovement($allMovements);
            $beforePeriod = $allMovements->filter(function (StockMovement $movement) use ($filters): bool {
                if (empty($filters['date_from'])) {
                    return false;
                }

                return $movement->movement_date?->format('Y-m-d') < $filters['date_from'];
            });

            $openingStock = round($initialStock + $this->netMovement($beforePeriod), 2);
            $balance = $openingStock;

            foreach ($periodMovements as $movement) {
                $balance += $movement->movement_type === 'MASUK'
                    ? (float) $movement->quantity
                    : -(float) $movement->quantity;
                $balance = round($balance, 2);
                $balanceByMovement[$movement->movement_id] = $balance;
            }
        }

        $movements = $periodMovements
            ->when(
                ! empty($filters['movement_type']),
                fn (Collection $items): Collection => $items
                    ->where('movement_type', $filters['movement_type'])
                    ->values(),
            )
            ->values();

        $rows = $movements->map(function (StockMovement $movement) use ($balanceByMovement): array {
            $purchaseId = $movement->purchaseDetail?->purchase_id;

            return [
                'movement_id' => $movement->movement_id,
                'movement_date' => $movement->movement_date?->format('Y-m-d H:i:s'),
                'material_id' => $movement->material_id,
                'material_name' => $movement->material?->material_name,
                'unit_name' => $movement->material?->unit_name,
                'movement_type' => $movement->movement_type,
                'quantity' => (float) $movement->quantity,
                'balance' => $balanceByMovement[$movement->movement_id] ?? null,
                'purchase_id' => $purchaseId,
                'production_order_id' => $movement->production_order_id,
                'notes' => $movement->notes,
            ];
        })->values()->all();

        return [
            'type' => 'stock-card',
            'period' => $this->period($filters),
            'summary' => [
                'material' => $material !== null ? [
                    'material_id' => $material->material_id,
                    'material_name' => $material->material_name,
                    'unit_name' => $material->unit_name,
                    'current_stock' => (float) $material->current_stock,
                ] : null,
                'movement_count' => $movements->count(),
                'total_in' => round((float) $movements
                    ->where('movement_type', 'MASUK')
                    ->sum(fn (StockMovement $movement): float => (float) $movement->quantity), 2),
                'total_out' => round((float) $movements
                    ->where('movement_type', 'KELUAR')
                    ->sum(fn (StockMovement $movement): float => (float) $movement->quantity), 2),
                'opening_stock' => $openingStock,
                'closing_stock' => $balance,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Employee productivity per production stage (FR-R2).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function productivity(array $filters): array
    {
        $progress = ProductionProgress::query()
            ->with('employee')
            ->when(! empty($filters['date_from']), function ($query) use ($filters): void {
                $query->whereDate('started_at', '>=', $filters['date_from']);
            })
            ->when(! empty($filters['date_to']), function ($query) use ($filters): void {
                $query->whereDate('started_at', '<=', $filters['date_to']);
            })
            ->when(! empty($filters['employee_id']), function ($query) use ($filters): void {
                $query->where('employee_id', (int) $filters['employee_id']);
            })
            ->when(! empty($filters['stage_code']), function ($query) use ($filters): void {
                $query->where('stage_code', $filters['stage_code']);
            })
            ->orderBy('started_at')
            ->orderBy('progress_id')
            ->get();

        $groups = $progress->groupBy(
            fn (ProductionProgress $item): string => $item->employee_id.'|'.$item->stage_code,
        );

        $rows = $groups->map(function (Collection $items): array {
            /** @var ProductionProgress $first */
            $first = $items->first();
            $completed = $items->filter(
                fn (ProductionProgress $item): bool => $item->status === 'Selesai',
            );
            $duration = $completed->sum(function (ProductionProgress $item): int {
                if ($item->started_at === null || $item->completed_at === null) {
                    return 0;
                }

                return (int) round($item->started_at->diffInMinutes($item->completed_at));
            });
            $lastCompleted = $completed
                ->pluck('completed_at')
                ->filter()
                ->sortDesc()
                ->first();

            return [
                'employee_id' => $first->employee_id,
                'employee_name' => $first->employee?->name,
                'position' => $first->employee?->position,
                'stage_code' => $first->stage_code,
                'stage_label' => self::STAGE_LABELS[$first->stage_code] ?? $first->stage_code,
                'sessions' => $items->count(),
                'completed_sessions' => $completed->count(),
                'completed_quantity' => (int) $completed->sum(
                    fn (ProductionProgress $item): int => (int) $item->completed_quantity,
                ),
                'duration_minutes' => $duration,
                'last_completed_at' => $lastCompleted?->format('Y-m-d H:i:s'),
            ];
        })->values();

        $stageOrder = array_flip(self::STAGES);
        $rows = $rows->sort(function (array $left, array $right) use ($stageOrder): int {
            return [
                $left['employee_name'],
                $stageOrder[$left['stage_code']] ?? 99,
            ] <=> [
                $right['employee_name'],
                $stageOrder[$right['stage_code']] ?? 99,
            ];
        })->values()->all();

        return [
            'type' => 'productivity',
            'period' => $this->period($filters),
            'summary' => [
                'employee_count' => $progress->pluck('employee_id')->unique()->count(),
                'session_count' => $progress->count(),
                'completed_sessions' => $progress
                    ->where('status', 'Selesai')
                    ->count(),
                'completed_quantity' => (int) $progress
                    ->where('status', 'Selesai')
                    ->sum(fn (ProductionProgress $item): int => (int) $item->completed_quantity),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Normalize a report into headings, display rows, and summary lines.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function tableFor(string $type, array $report): array
    {
        return match ($type) {
            'sales' => $this->salesTable($report),
            'purchases' => $this->purchasesTable($report),
            'stock-card' => $this->stockCardTable($report),
            'productivity' => $this->productivityTable($report),
            default => throw new \InvalidArgumentException('Jenis laporan tidak dikenal.'),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filename(string $type, array $filters, string $extension): string
    {
        $base = match ($type) {
            'sales' => 'laporan-penjualan',
            'purchases' => 'laporan-pembelian',
            'stock-card' => 'kartu-stok',
            'productivity' => 'laporan-produktivitas',
            default => 'laporan',
        };

        $from = $filters['date_from'] ?? 'awal';
        $to = $filters['date_to'] ?? 'akhir';

        return $base.'-'.$from.'-sd-'.$to.'.'.$extension;
    }

    /**
     * Build the text lines used by the PDF generator.
     *
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    public function pdfLines(string $type, array $report): array
    {
        $table = $this->tableFor($type, $report);
        $widths = $table['widths'];
        $lines = [$table['period']];

        foreach ($table['summary'] as $summaryLine) {
            $lines[] = $summaryLine;
        }

        $lines[] = '';
        $lines[] = $this->rowLine($table['headings'], $widths);
        $lines[] = str_repeat('-', array_sum($widths) + count($widths) - 1);

        if ($table['rows'] === []) {
            $lines[] = 'Tidak ada data pada periode/filter ini.';
        } else {
            foreach ($table['rows'] as $row) {
                $lines[] = $this->rowLine($row, $widths);
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{date_from: string|null, date_to: string|null}
     */
    private function period(array $filters): array
    {
        return [
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ];
    }

    private function netMovement(Collection $movements): float
    {
        return (float) $movements->sum(
            fn (StockMovement $movement): float => $movement->movement_type === 'MASUK'
                ? (float) $movement->quantity
                : -(float) $movement->quantity,
        );
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function salesTable(array $report): array
    {
        $summary = $report['summary'];

        return [
            'title' => 'Laporan Penjualan',
            'period' => $this->periodText($report['period']),
            'summary' => [
                'Total pesanan: '.$summary['order_count']
                    .' | Selesai: '.$summary['completed_orders']
                    .' | Dibatalkan: '.$summary['cancelled_orders'],
                'Nilai penjualan: '.$this->rupiah((float) $summary['total_sales']),
                'Pembayaran diterima: '.$this->rupiah((float) $summary['paid_amount']),
                'Sisa tagihan: '.$this->rupiah((float) $summary['remaining_amount']),
            ],
            'headings' => [
                'No', 'Tanggal', 'Jatuh Tempo', 'Pelanggan', 'Status',
                'Total (Rp)', 'Dibayar (Rp)', 'Sisa (Rp)',
            ],
            'widths' => [8, 10, 10, 20, 9, 12, 12, 11],
            'rows' => array_map(fn (array $row): array => [
                (string) $row['order_id'],
                (string) $row['order_date'],
                (string) $row['due_date'],
                (string) ($row['customer'] ?? '-'),
                (string) $row['status'],
                $this->number((float) $row['total_amount']),
                $this->number((float) $row['paid_amount']),
                $this->number((float) $row['remaining_amount']),
            ], $report['rows']),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function purchasesTable(array $report): array
    {
        $summary = $report['summary'];

        return [
            'title' => 'Laporan Pembelian',
            'period' => $this->periodText($report['period']),
            'summary' => [
                'Total pembelian: '.$summary['purchase_count']
                    .' | Dibatalkan: '.$summary['cancelled_purchases'],
                'Nilai pembelian: '.$this->rupiah((float) $summary['total_purchases']),
                'Diterima: '.$this->rupiah((float) $summary['received_total'])
                    .' | Dipesan: '.$this->rupiah((float) $summary['ordered_total']),
            ],
            'headings' => ['No', 'Tanggal', 'Pemasok', 'Status', 'Item', 'Total (Rp)'],
            'widths' => [8, 10, 24, 10, 8, 14],
            'rows' => array_map(fn (array $row): array => [
                (string) $row['purchase_id'],
                (string) $row['purchase_date'],
                (string) ($row['supplier'] ?? '-'),
                (string) $row['status'],
                (string) $row['item_count'],
                $this->number((float) $row['total_amount']),
            ], $report['rows']),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function stockCardTable(array $report): array
    {
        $summary = $report['summary'];
        $material = $summary['material'];
        $lines = [];

        if ($material !== null) {
            $lines[] = 'Bahan: '.$material['material_name'].' ('.$material['unit_name'].')';
            $lines[] = 'Stok awal: '.$this->number((float) ($summary['opening_stock'] ?? 0))
                .' | Stok akhir: '.$this->number((float) ($summary['closing_stock'] ?? 0))
                .' | Stok saat ini: '.$this->number((float) $material['current_stock']);
        } else {
            $lines[] = 'Bahan: semua bahan';
        }

        $lines[] = 'Mutasi: '.$summary['movement_count']
            .' | Masuk: '.$this->number((float) $summary['total_in'])
            .' | Keluar: '.$this->number((float) $summary['total_out']);

        return [
            'title' => 'Kartu Stok Bahan',
            'period' => $this->periodText($report['period']),
            'summary' => $lines,
            'headings' => [
                'Tanggal', 'Bahan', 'Sat.', 'Tipe', 'Jumlah', 'Saldo', 'Sumber', 'Catatan',
            ],
            'widths' => [15, 17, 6, 7, 10, 10, 16, 16],
            'rows' => array_map(function (array $row): array {
                $source = '-';

                if ($row['purchase_id'] !== null) {
                    $source = 'Pembelian #'.$row['purchase_id'];
                } elseif ($row['production_order_id'] !== null) {
                    $source = 'Produksi #'.$row['production_order_id'];
                }

                return [
                    (string) $row['movement_date'],
                    (string) ($row['material_name'] ?? '-'),
                    (string) ($row['unit_name'] ?? '-'),
                    (string) $row['movement_type'],
                    $this->number((float) $row['quantity']),
                    $row['balance'] === null ? '-' : $this->number((float) $row['balance']),
                    $source,
                    (string) ($row['notes'] ?? '-'),
                ];
            }, $report['rows']),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function productivityTable(array $report): array
    {
        $summary = $report['summary'];

        return [
            'title' => 'Laporan Produktivitas Karyawan',
            'period' => $this->periodText($report['period']),
            'summary' => [
                'Karyawan: '.$summary['employee_count']
                    .' | Sesi: '.$summary['session_count']
                    .' | Sesi selesai: '.$summary['completed_sessions'],
                'Qty selesai: '.$summary['completed_quantity'],
            ],
            'headings' => [
                'Karyawan', 'Jabatan', 'Tahap', 'Sesi', 'Selesai',
                'Qty', 'Menit', 'Selesai Terakhir',
            ],
            'widths' => [18, 14, 10, 6, 7, 9, 9, 16],
            'rows' => array_map(fn (array $row): array => [
                (string) ($row['employee_name'] ?? '-'),
                (string) ($row['position'] ?? '-'),
                (string) $row['stage_label'],
                (string) $row['sessions'],
                (string) $row['completed_sessions'],
                (string) $row['completed_quantity'],
                (string) $row['duration_minutes'],
                (string) ($row['last_completed_at'] ?? '-'),
            ], $report['rows']),
        ];
    }

    /**
     * @param  array{date_from: string|null, date_to: string|null}  $period
     */
    private function periodText(array $period): string
    {
        return 'Periode: '.($period['date_from'] ?? 'awal data')
            .' s.d. '.($period['date_to'] ?? 'akhir data');
    }

    private function rupiah(float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }

    private function number(float $value): string
    {
        $formatted = number_format($value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    /**
     * @param  list<string|int|float|null>  $cells
     * @param  list<int>  $widths
     */
    private function rowLine(array $cells, array $widths): string
    {
        $line = '';

        foreach ($widths as $index => $width) {
            $value = (string) ($cells[$index] ?? '');
            $value = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
            $value = mb_substr($value, 0, $width);
            $line .= str_pad($value, $width);

            if ($index < count($widths) - 1) {
                $line .= ' ';
            }
        }

        return rtrim($line);
    }
}
