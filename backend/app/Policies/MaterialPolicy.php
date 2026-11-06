<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModuleAccess;

class MaterialPolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'materials';
    }
}
