<?php

namespace App\Repositories;

use App\Exceptions\CustomModelNotFoundException;
use App\Models\CashOutMethod;

class CashOutMethodRepository
{
    public static function find($uuid)
    {
        $method = CashOutMethod::whereUuid($uuid)->first();
        if (blank($method)) {
            throw new CustomModelNotFoundException('No cash out method found for id specified');
        }

        return $method;
    }
}
