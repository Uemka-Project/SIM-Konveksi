<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\AccessMatrix;

trait ChecksModuleAccess
{
    /**
     * The AccessMatrix module handled by the policy.
     */
    abstract protected function module(): string;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'R');
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'R');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'C');
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'U');
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'D');
    }

    protected function allows(User $user, string $action): bool
    {
        return AccessMatrix::can($user, $this->module(), $action);
    }
}
