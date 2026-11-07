<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksModuleAccess;
use App\Support\AccessMatrix;

class ProductionOrderPolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'production';
    }

    /**
     * Editing an SPK follows the production module. Production staff may
     * update the status, the owner keeps full control.
     */
    public function update(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'U');
    }

    /**
     * Handing materials over is a warehouse action that also writes stock
     * movements. The owner keeps full access.
     */
    public function issueMaterials(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'U')
            && (AccessMatrix::can($user, 'stock', 'C') || $this->allows($user, 'D'));
    }

    /**
     * Only production staff (or the owner) may record stage progress.
     */
    public function recordProgress(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'U')
            && ($this->allows($user, 'D') || $user->hasRole('produksi'));
    }
}
