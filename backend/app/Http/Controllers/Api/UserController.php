<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\UserRolesRequest;
use App\Http\Requests\Access\UserStoreRequest;
use App\Http\Requests\Access\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()
            ->with(['roles', 'employee'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('search')).'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('username', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhereHas('employee', fn ($query) => $query->where('name', 'like', $term));
                });
            })
            ->when($request->has('is_active') && $request->query('is_active') !== '', function ($query) use ($request): void {
                $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOL));
            })
            ->when($request->filled('role_id'), function ($query) use ($request): void {
                $query->whereHas('roles', fn ($query) => $query->where('roles.role_id', $request->integer('role_id')));
            })
            ->orderBy('username');

        $paginator = $query->paginate(
            (int) min(max((int) $request->query('per_page', 20), 1), 100)
        )->withQueryString();

        return response()->json([
            'data' => UserResource::collection($paginator->items())->toArray($request),
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

    public function store(UserStoreRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'employee_id' => $data['employee_id'] ?? null,
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->roles()->sync($data['role_ids']);

            return $user;
        });

        $user->load(['roles', 'employee']);

        return response()->json([
            'message' => 'Pengguna berhasil ditambahkan.',
            'data' => (new UserResource($user))->toArray($request),
        ], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load(['roles', 'employee']);

        return response()->json([
            'data' => (new UserResource($user))->toArray($request),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();

        if (($data['is_active'] ?? true) === false && $user->user_id === $request->user()->user_id) {
            return response()->json([
                'message' => 'Anda tidak dapat menonaktifkan akun sendiri.',
                'errors' => [
                    'is_active' => ['Anda tidak dapat menonaktifkan akun sendiri.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($user, $data): void {
            $attributes = [
                'employee_id' => $data['employee_id'] ?? null,
                'username' => $data['username'],
                'email' => $data['email'],
                'is_active' => $data['is_active'] ?? $user->is_active,
            ];

            if (! empty($data['password'])) {
                $attributes['password_hash'] = $data['password'];
            }

            $user->update($attributes);

            if (array_key_exists('role_ids', $data)) {
                $user->roles()->sync($data['role_ids']);
            }
        });

        $user->load(['roles', 'employee']);

        return response()->json([
            'message' => 'Pengguna berhasil diperbarui.',
            'data' => (new UserResource($user))->toArray($request),
        ]);
    }

    /**
     * Users referenced by history are deactivated instead of deleted.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->user_id === $request->user()->user_id) {
            return response()->json([
                'message' => 'Anda tidak dapat menghapus akun sendiri.',
            ], 422);
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Pengguna berhasil dinonaktifkan dan seluruh sesinya dicabut.',
            'data' => (new UserResource($user->fresh()->load(['roles', 'employee'])))->toArray($request),
        ]);
    }

    public function syncRoles(UserRolesRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user->roles()->sync($request->validated('role_ids'));
        $user->load(['roles', 'employee']);

        return response()->json([
            'message' => 'Peran pengguna berhasil diperbarui.',
            'data' => (new UserResource($user))->toArray($request),
        ]);
    }
}
