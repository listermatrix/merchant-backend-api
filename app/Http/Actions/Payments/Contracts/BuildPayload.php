<?php

namespace App\Http\Actions\Payments\Contracts;

interface BuildPayload
{
    /** Construct payment payload */
    public function buildPayload(array $data = []): array;
}
