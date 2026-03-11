<?php

namespace App\Http\Actions\Payments\Saana;

use App\Http\Actions\Payments\Cellulant\CellulantBankProcessor;
use App\Http\Actions\Payments\Cellulant\CellulantMomoProcessor;
use App\Http\Actions\Payments\Cellulant\CellulantTzMomoProcessor;

class SaanaProcessorFactory
{
    public static function getProcessor($method): SaanaBankProcessor
    {
        return new SaanaBankProcessor;
    }
}
