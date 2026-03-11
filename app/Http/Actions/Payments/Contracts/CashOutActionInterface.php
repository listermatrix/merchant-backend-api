<?php

namespace App\Http\Actions\Payments\Contracts;

interface CashOutActionInterface
{
    /** The  url to send the cashout request to*/
    public function getUrl(): string;

    /** Construct payment payload */
    public function buildPayload(array $data = []): array;

    /** Construct payment response */
    public function buildResponse(string $data): array;

    /** Retrieves the cashout methods selected by user */
    public function getCashOutMethod();

    /** Execute task */
    public function execute();

    /** Reduce wallets balance */
    public function reduceBalance($wallet, $total_amount);

    /** Return the status code of the response */
    public function getStatusCodes(array $data): array;
}
