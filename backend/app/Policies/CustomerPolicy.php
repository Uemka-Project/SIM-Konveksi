<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModuleAccess;

class CustomerPolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'customers';
    }
}
