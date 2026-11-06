<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModuleAccess;

class EmployeePolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'employees';
    }
}
