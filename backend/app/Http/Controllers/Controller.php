<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Return a paginated JSON response wrapped in a stable envelope.
     *
     * @param  class-string<\Illuminate\Http\Resources\Json\JsonResource>  $resource
     */
    protected function paginated(Builder $query, Request $request, string $resource): JsonResponse
    {
        $perPage = (int) min(max((int) $request->query('per_page', 20), 1), 100);

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $resource::collection($paginator->items())->toArray($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }
}
