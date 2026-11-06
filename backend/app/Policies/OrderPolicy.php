<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModuleAccess;

class OrderPolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'orders';
    }
}
