<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModuleAccess;

class SupplierPolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'suppliers';
    }
}
