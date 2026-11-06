<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModuleAccess;

class PaymentPolicy
{
    use ChecksModuleAccess;

    protected function module(): string
    {
        return 'payments';
    }
}
