<?php

namespace App\Http\Actions\Payments\Contracts;

interface BuildResponse
{
    /** Construct payment response */
    public function buildResponse(string $data): array;
}
