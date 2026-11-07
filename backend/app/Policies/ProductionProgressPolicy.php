<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksModuleAccess;

class ProductionProgressPolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'production';
    }

    /**
     * Finishing or correcting a progress row is done by production staff.
     * The owner keeps full access through the delete permission.
     */
    public function update(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'U')
            && ($this->allows($user, 'D') || $user->hasRole('produksi'));
    }
}
