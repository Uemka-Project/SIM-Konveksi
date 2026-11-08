<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Material;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ProductionOrder;
use App\Models\ProductionProgress;
use App\Models\StockMovement;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    /**
     * Create a production order (SPK) for one order item. FR-P1 requires the
     * order to be Diproses with a DP, and D1 allows several batches as long as
     * their total planned quantity does not exceed the ordered quantity.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createForOrderDetail(OrderDetail $orderDetail, array $attributes): ProductionOrder
    {
        return DB::transaction(function () use ($orderDetail, $attributes): ProductionOrder {
            $detail = OrderDetail::query()
                ->whereKey($orderDetail->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::query()
                ->whereKey($detail->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== 'Diproses') {
                $message = $order->status === 'Baru'
                    ? 'Perintah produksi hanya dapat dibuat setelah pesanan memiliki DP dan berstatus Diproses.'
                    : "Pesanan berstatus {$order->status} tidak dapat dibuatkan perintah produksi.";

                throw new BusinessRuleException($message, ['status' => [$message]]);
            }

            if (! $order->payments()->where('payment_type', 'DP')->exists()) {
                $message = 'Perintah produksi hanya dapat dibuat setelah pembayaran DP dicatat.';

                throw new BusinessRuleException($message, ['payment' => [$message]]);
            }

            $plannedQuantity = (int) $attributes['planned_quantity'];
            $existingQuantity = (int) $detail->productionOrders()
                ->lockForUpdate()
                ->sum('planned_quantity');
            $remainingQuantity = (int) $detail->quantity - $existingQuantity;

            if ($plannedQuantity > $remainingQuantity) {
                $message = $remainingQuantity > 0
                    ? "Jumlah rencana produksi melebihi sisa item pesanan ({$remainingQuantity})."
                    : 'Seluruh jumlah item pesanan sudah dialokasikan ke perintah produksi.';

                throw new BusinessRuleException($message, ['planned_quantity' => [$message]]);
            }

            $startDate = $attributes['start_date'] ?? null;
            $targetDate = $attributes['target_date'];

            if ($startDate !== null && Carbon::parse($targetDate)->lt(Carbon::parse($startDate))) {
                $message = 'Tanggal target tidak boleh lebih awal dari tanggal mulai.';

                throw new BusinessRuleException($message, ['target_date' => [$message]]);
            }

            $production = ProductionOrder::create([
                'order_detail_id' => $detail->order_detail_id,
                'planned_quantity' => $plannedQuantity,
                'status' => 'Menunggu',
                'start_date' => $startDate,
                'target_date' => $targetDate,
            ]);

            AuditLogger::record('production.created', 'SPK #'.$production->production_order_id
                .' dibuat untuk item pesanan #'.$detail->order_detail_id.'.', [
                    'production_order_id' => $production->production_order_id,
                    'order_detail_id' => $detail->order_detail_id,
                    'planned_quantity' => $plannedQuantity,
                ]);

            return $production;
        });
    }

    /**
     * FR-P2: BOM requirements for one batch, compared with current stock.
     *
     * @return array<string, mixed>
     */
    public function requirements(ProductionOrder $productionOrder): array
    {
        $detail = $productionOrder->orderDetail()
            ->with('variant.materials')
            ->firstOrFail();

        $required = $this->requiredMaterials($detail, (int) $productionOrder->planned_quantity);

        $items = [];

        foreach ($required as $materialId => $requirement) {
            /** @var Material $material */
            $material = $requirement['material'];
            $needed = (float) $requirement['quantity'];
            $stock = (float) $material->current_stock;
            $shortage = max(0, round($needed - $stock, 2));

            $items[] = [
                'material_id' => $materialId,
                'material_name' => $material->material_name,
                'unit_name' => $material->unit_name,
                'required_quantity' => $needed,
                'current_stock' => $stock,
                'minimum_stock' => (float) $material->minimum_stock,
                'shortage_quantity' => $shortage,
                'is_sufficient' => $shortage <= 0,
            ];
        }

        usort(
            $items,
            fn (array $left, array $right): int => strcmp(
                $left['material_name'],
                $right['material_name'],
            ),
        );

        return [
            'production_order_id' => $productionOrder->production_order_id,
            'planned_quantity' => (int) $productionOrder->planned_quantity,
            'items' => $items,
            'is_sufficient' => ! in_array(false, array_column($items, 'is_sufficient'), true),
        ];
    }

    /**
     * FR-P4: hand materials over to production. One KELUAR movement is written
     * per BOM component, stock is reduced, and the SPK moves to Berjalan in the
     * same transaction. Any shortage cancels the whole operation.
     */
    public function issueMaterials(ProductionOrder $productionOrder): ProductionOrder
    {
        return DB::transaction(function () use ($productionOrder): ProductionOrder {
            $production = ProductionOrder::query()
                ->whereKey($productionOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($production->status !== 'Menunggu') {
                $message = "Perintah produksi berstatus {$production->status} tidak dapat diserahkan bahannya.";

                throw new BusinessRuleException($message, ['status' => [$message]]);
            }

            $detail = OrderDetail::query()
                ->whereKey($production->order_detail_id)
                ->lockForUpdate()
                ->firstOrFail();

            $required = $this->requiredMaterials($detail, (int) $production->planned_quantity);

            if ($required === []) {
                $message = 'Varian pada item pesanan belum memiliki komponen BOM, sehingga bahan tidak dapat diserahkan.';

                throw new BusinessRuleException($message, ['bom' => [$message]]);
            }

            $materials = Material::query()
                ->whereIn('material_id', array_keys($required))
                ->lockForUpdate()
                ->get()
                ->keyBy('material_id');

            $shortages = [];

            foreach ($required as $materialId => $requirement) {
                $material = $materials->get($materialId);

                if ($material === null) {
                    $shortages[] = 'Bahan #'.$materialId.' tidak ditemukan.';

                    continue;
                }

                $needed = (float) $requirement['quantity'];
                $stock = (float) $material->current_stock;

                if ($stock + 0.0001 < $needed) {
                    $shortages[] = $material->material_name
                        .' kurang '.number_format($needed - $stock, 2, ',', '.')
                        .' '.$material->unit_name.'.';
                }
            }

            if ($shortages !== []) {
                $message = 'Stok bahan tidak mencukupi untuk perintah produksi ini.';

                throw new BusinessRuleException($message, ['materials' => $shortages]);
            }

            $issuedAt = now();

            foreach ($required as $materialId => $requirement) {
                $quantity = (float) $requirement['quantity'];
                $material = $materials->get($materialId);

                StockMovement::create([
                    'material_id' => $materialId,
                    'movement_type' => 'KELUAR',
                    'quantity' => $quantity,
                    'movement_date' => $issuedAt,
                    'purchase_detail_id' => null,
                    'production_order_id' => $production->production_order_id,
                    'notes' => 'Serah bahan SPK #'.$production->production_order_id,
                ]);

                $material->decrement('current_stock', $quantity);
            }

            $production->update([
                'status' => 'Berjalan',
                'start_date' => $production->start_date ?? $issuedAt->toDateString(),
            ]);

            AuditLogger::record('production.materials_issued', 'Bahan SPK #'
                .$production->production_order_id.' diserahkan; status menjadi Berjalan.', [
                    'production_order_id' => $production->production_order_id,
                    'material_count' => count($required),
                ]);

            return $production;
        });
    }

    /**
     * FR-P3: record one stage progress row. A rework is simply another row on
     * the same stage. FR-P5: when all four stages have a Selesai row, the SPK
     * itself becomes Selesai.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordProgress(ProductionOrder $productionOrder, array $attributes): ProductionProgress
    {
        return DB::transaction(function () use ($productionOrder, $attributes): ProductionProgress {
            $production = ProductionOrder::query()
                ->whereKey($productionOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($production->status !== 'Berjalan') {
                $message = 'Progres hanya dapat dicatat pada perintah produksi berstatus Berjalan.';

                throw new BusinessRuleException($message, ['status' => [$message]]);
            }

            $status = $attributes['status'] ?? 'Proses';
            $startedAt = Carbon::parse($attributes['started_at']);
            $completedAt = $this->completedAtFor(
                $status,
                $attributes['completed_at'] ?? null,
                $startedAt,
            );

            $progress = ProductionProgress::create([
                'production_order_id' => $production->production_order_id,
                'employee_id' => (int) $attributes['employee_id'],
                'stage_code' => $attributes['stage_code'],
                'status' => $status,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'completed_quantity' => (int) ($attributes['completed_quantity'] ?? 0),
            ]);

            $this->syncStatus($production);

            AuditLogger::record('production.progress_recorded', 'Progres '
                .$progress->stage_code.' ('.$progress->status.') pada SPK #'
                .$production->production_order_id.' dicatat.', [
                    'progress_id' => $progress->progress_id,
                    'production_order_id' => $production->production_order_id,
                    'stage_code' => $progress->stage_code,
                    'status' => $progress->status,
                ]);

            return $progress;
        });
    }

    /**
     * PATCH progress/{id}: finish or correct an existing row.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateProgress(
        ProductionProgress $productionProgress,
        array $attributes,
    ): ProductionProgress {
        return DB::transaction(function () use ($productionProgress, $attributes): ProductionProgress {
            $progress = ProductionProgress::query()
                ->whereKey($productionProgress->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $production = ProductionOrder::query()
                ->whereKey($progress->production_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($production->status === 'Menunggu') {
                $message = 'Progres belum dapat diubah karena bahan belum diserahkan.';

                throw new BusinessRuleException($message, ['status' => [$message]]);
            }

            $status = $attributes['status'] ?? $progress->status;
            $startedAt = Carbon::parse($attributes['started_at'] ?? $progress->started_at);
            $completedAtInput = array_key_exists('completed_at', $attributes)
                ? $attributes['completed_at']
                : $progress->completed_at?->format('Y-m-d H:i:s');
            $completedAt = $this->completedAtFor($status, $completedAtInput, $startedAt);

            $progress->update([
                'employee_id' => (int) ($attributes['employee_id'] ?? $progress->employee_id),
                'stage_code' => $attributes['stage_code'] ?? $progress->stage_code,
                'status' => $status,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'completed_quantity' => (int) ($attributes['completed_quantity']
                    ?? $progress->completed_quantity),
            ]);

            $this->syncStatus($production);

            AuditLogger::record('production.progress_updated', 'Progres #'
                .$progress->progress_id.' pada SPK #'.$production->production_order_id
                .' diperbarui.', [
                    'progress_id' => $progress->progress_id,
                    'production_order_id' => $production->production_order_id,
                    'stage_code' => $progress->stage_code,
                    'status' => $progress->status,
                ]);

            return $progress;
        });
    }

    /**
     * Build the raw BOM requirement list for one batch.
     *
     * @return array<int, array{material: Material, quantity: float}>
     */
    private function requiredMaterials(OrderDetail $detail, int $plannedQuantity): array
    {
        $variant = $detail->variant;

        if ($variant === null) {
            return [];
        }

        if (! $variant->relationLoaded('materials')) {
            $variant->load('materials');
        }

        $required = [];

        foreach ($variant->materials as $material) {
            $quantity = round(
                $plannedQuantity * (float) $material->pivot->quantity_per_unit,
                2,
            );

            if ($quantity <= 0) {
                continue;
            }

            $materialId = $material->material_id;

            if (! isset($required[$materialId])) {
                $required[$materialId] = [
                    'material' => $material,
                    'quantity' => 0.0,
                ];
            }

            $required[$materialId]['quantity'] = round(
                $required[$materialId]['quantity'] + $quantity,
                2,
            );
        }

        return $required;
    }

    private function completedAtFor(
        string $status,
        mixed $completedAt,
        Carbon $startedAt,
    ): ?Carbon {
        if ($status !== 'Selesai') {
            return null;
        }

        if ($completedAt === null || $completedAt === '') {
            $message = 'Waktu selesai wajib diisi bila progres berstatus Selesai.';

            throw new BusinessRuleException($message, ['completed_at' => [$message]]);
        }

        $completed = Carbon::parse($completedAt);

        if ($completed->lt($startedAt)) {
            $message = 'Waktu selesai tidak boleh lebih awal dari waktu mulai.';

            throw new BusinessRuleException($message, ['completed_at' => [$message]]);
        }

        return $completed;
    }

    private function syncStatus(ProductionOrder $production): void
    {
        $completedStages = $production->progress()
            ->where('status', 'Selesai')
            ->pluck('stage_code')
            ->unique();

        $allStagesCompleted = collect(['CUTTING', 'SEWING', 'FINISHING', 'QC'])
            ->every(fn (string $stage): bool => $completedStages->contains($stage));

        if ($allStagesCompleted) {
            if ($production->status !== 'Selesai') {
                $production->update(['status' => 'Selesai']);
            }

            return;
        }

        if ($production->status === 'Selesai') {
            $production->update(['status' => 'Berjalan']);
        }
    }
}
