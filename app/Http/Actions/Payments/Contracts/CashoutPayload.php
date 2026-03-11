<?php

namespace App\Http\Actions\Payments\Contracts;

use App\Models\CashOutMethod;

interface CashoutPayload
{
    public function getPayload(CashOutMethod $cashout): array;
}
