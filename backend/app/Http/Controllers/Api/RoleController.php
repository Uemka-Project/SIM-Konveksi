<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    /**
     * Roles are reference data; only user managers may read them.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount('users')
            ->orderBy('role_id')
            ->get();

        return response()->json([
            'data' => RoleResource::collection($roles)->toArray(request()),
        ]);
    }
}
