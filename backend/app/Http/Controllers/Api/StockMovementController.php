<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\Material;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockMovement::class);

        return $this->paginated($this->query($request), $request, StockMovementResource::class);
    }

    /**
     * Stock card for one material (FR-S3).
     */
    public function ledger(Request $request, Material $material): JsonResponse
    {
        $this->authorize('view', $material);
        $this->authorize('viewAny', StockMovement::class);

        $request->query->set('material_id', $material->material_id);

        return $this->paginated($this->query($request), $request, StockMovementResource::class);
    }

    private function query(Request $request): Builder
    {
        return StockMovement::query()
            ->with(['material', 'purchaseDetail.purchase'])
            ->when($request->filled('material_id'), function ($query) use ($request): void {
                $query->where('material_id', (int) $request->query('material_id'));
            })
            ->when($request->filled('movement_type'), function ($query) use ($request): void {
                $query->where('movement_type', $request->query('movement_type'));
            })
            ->when($request->filled('date_from'), function ($query) use ($request): void {
                $query->whereDate('movement_date', '>=', $request->query('date_from'));
            })
            ->when($request->filled('date_to'), function ($query) use ($request): void {
                $query->whereDate('movement_date', '<=', $request->query('date_to'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('search')).'%';

                $query->whereHas('material', function ($query) use ($term): void {
                    $query->where('material_name', 'like', $term);
                });
            })
            ->orderByDesc('movement_date')
            ->orderByDesc('movement_id');
    }
}
