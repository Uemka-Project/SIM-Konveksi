<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModuleAccess;

class ProductVariantPolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'variants';
    }
}
