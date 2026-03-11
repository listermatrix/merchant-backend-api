<?php

namespace App\Http\Actions\Payments\Contracts;

interface CashoutProcessor
{
    public function process($type, $dto);
}
