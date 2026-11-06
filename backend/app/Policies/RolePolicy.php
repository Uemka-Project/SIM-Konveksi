<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModuleAccess;

class RolePolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'users';
    }
}
