<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\EmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $query = Employee::query()
            ->with(['user'])
            ->withCount('productionProgress')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('search')).'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('position', 'like', $term);
                });
            })
            ->when($request->has('active_status') && $request->query('active_status') !== '', function ($query) use ($request): void {
                $query->where('active_status', filter_var($request->query('active_status'), FILTER_VALIDATE_BOOL));
            })
            ->orderBy('name');

        return $this->paginated($query, $request, EmployeeResource::class);
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $employee = Employee::create($request->validated());

        return response()->json([
            'message' => 'Karyawan berhasil ditambahkan.',
            'data' => new EmployeeResource($employee),
        ], 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        $employee->load(['user'])->loadCount('productionProgress');

        return response()->json(['data' => new EmployeeResource($employee)]);
    }

    public function update(EmployeeRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $employee->update($request->validated());

        return response()->json([
            'message' => 'Karyawan berhasil diperbarui.',
            'data' => new EmployeeResource($employee),
        ]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->authorize('delete', $employee);

        $isUsed = $employee->user()->exists() || $employee->productionProgress()->exists();

        if ($isUsed) {
            $employee->update(['active_status' => false]);

            return response()->json([
                'message' => 'Karyawan sudah dipakai transaksi sehingga dinonaktifkan, bukan dihapus.',
                'data' => new EmployeeResource($employee->fresh()),
            ]);
        }

        $employee->delete();

        return response()->json(['message' => 'Karyawan berhasil dihapus.']);
    }
}
