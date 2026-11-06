<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModuleAccess;

class ProductPolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'products';
    }
}
