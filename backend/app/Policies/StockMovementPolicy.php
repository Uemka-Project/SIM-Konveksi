<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModuleAccess;

class StockMovementPolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'stock';
    }
}
