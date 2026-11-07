<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksModuleAccess;
use App\Support\AccessMatrix;

class PurchasePolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'purchases';
    }

    /**
     * Only roles that may create a purchase may edit its contents. Warehouse
     * staff are limited to receiving the document.
     */
    public function update(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'C');
    }

    /**
     * Admin and owner may cancel a purchase; warehouse staff may not.
     */
    public function cancel(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'C') || $this->allows($user, 'D');
    }

    /**
     * Receiving is a warehouse action. The owner keeps full access.
     */
    public function receive(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'U')
            && (AccessMatrix::can($user, 'stock', 'C') || $this->allows($user, 'D'));
    }
}
