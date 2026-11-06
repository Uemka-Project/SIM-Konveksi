<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()
            ->withCount('orders')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('search')).'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('name');

        return $this->paginated($query, $request, CustomerResource::class);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $customer = Customer::create($request->validated());

        return response()->json([
            'message' => 'Pelanggan berhasil ditambahkan.',
            'data' => new CustomerResource($customer),
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $customer->loadCount('orders');

        return response()->json(['data' => new CustomerResource($customer)]);
    }

    public function update(CustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $customer->update($request->validated());

        return response()->json([
            'message' => 'Pelanggan berhasil diperbarui.',
            'data' => new CustomerResource($customer),
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->orders()->exists()) {
            return response()->json([
                'message' => 'Pelanggan tidak dapat dihapus karena sudah memiliki pesanan.',
            ], 422);
        }

        $customer->delete();

        return response()->json(['message' => 'Pelanggan berhasil dihapus.']);
    }
}
