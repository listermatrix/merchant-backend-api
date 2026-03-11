<?php

namespace App\Http\Actions\Payments\Contracts;

interface GetStatusCode
{
    /** Return the status code of the response */
    public function getStatusCodes(array $data): array;
}
