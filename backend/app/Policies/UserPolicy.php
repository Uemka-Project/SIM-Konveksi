<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModuleAccess;

class UserPolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'users';
    }
}
