<?php

namespace App\Support;

use App\Models\User;

/**
 * Single source of truth for the role/action matrix in the PRD (section 7).
 *
 * Actions are stored as compact letters: C = create, R = read, U = update,
 * D = delete. A role with "CRU" may create, read, and update, but not delete.
 */
final class AccessMatrix
{
    /**
     * @var array<string, array<string, string>>
     */
    public const MATRIX = [
        'customers' => ['owner' => 'CRUD', 'admin' => 'CRUD', 'produksi' => 'R'],
        'suppliers' => ['owner' => 'CRUD', 'admin' => 'CRUD', 'gudang' => 'R'],
        'employees' => ['owner' => 'CRUD', 'admin' => 'R', 'produksi' => 'R'],
        'materials' => ['owner' => 'CRUD', 'admin' => 'R', 'produksi' => 'R', 'gudang' => 'CRU'],
        'products' => ['owner' => 'CRUD', 'admin' => 'R', 'produksi' => 'R', 'gudang' => 'R'],
        'variants' => ['owner' => 'CRUD', 'admin' => 'R', 'produksi' => 'R', 'gudang' => 'R'],
        'bom' => ['owner' => 'CRUD', 'admin' => 'R', 'produksi' => 'CRU', 'gudang' => 'R'],
        'users' => ['owner' => 'CRUD'],
        'orders' => ['owner' => 'CRUD', 'admin' => 'CRU', 'produksi' => 'R', 'gudang' => 'R'],
        'payments' => ['owner' => 'CRUD', 'admin' => 'CR'],
        'purchases' => ['owner' => 'CRUD', 'admin' => 'CRU', 'gudang' => 'RU'],
        'production' => ['owner' => 'CRUD', 'admin' => 'CRU', 'produksi' => 'RU', 'gudang' => 'RU'],
        'stock' => ['owner' => 'R', 'admin' => 'R', 'produksi' => 'R', 'gudang' => 'CR'],
        'reports' => ['owner' => 'R', 'admin' => 'R', 'produksi' => 'R', 'gudang' => 'R'],
    ];

    /**
     * Determine whether the user may perform the given action on a module.
     *
     * @param  string  $action  One of R, C, U, D (case-insensitive).
     */
    public static function can(User $user, string $module, string $action): bool
    {
        $action = strtoupper($action);

        return str_contains(self::actionsFor($user, $module), $action);
    }

    /**
     * Return the union of the user's permissions, e.g. ['customers' => 'CRU'].
     *
     * @return array<string, string>
     */
    public static function permissions(User $user): array
    {
        $permissions = [];

        foreach (array_keys(self::MATRIX) as $module) {
            $permissions[$module] = self::actionsFor($user, $module);
        }

        return $permissions;
    }

    /**
     * Return the action letters the user's roles grant for a module.
     */
    private static function actionsFor(User $user, string $module): string
    {
        $user->loadMissing('roles');

        $actions = '';

        foreach ($user->roles as $role) {
            $allowed = self::MATRIX[$module][$role->role_name] ?? '';

            foreach (str_split($allowed) as $letter) {
                if (! str_contains($actions, $letter)) {
                    $actions .= $letter;
                }
            }
        }

        return $actions;
    }
}
