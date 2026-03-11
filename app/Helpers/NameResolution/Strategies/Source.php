<?php

namespace App\Helpers\NameResolution\Strategies;

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Arr;

abstract class Source
{
    protected string $countryCode;

    protected ?string $processor;

    protected string $accountId;

    protected ?string $accountCode;

    protected User|Client $entity;

    protected array $payload;

    abstract public function canHandlePayload(): bool;

    abstract public function handle();

    public function __construct(array $data)
    {
        $data = (object)$data;
        $this->countryCode = $data->country_code;
        $this->processor = $data->processor;
        $this->accountId = Arr::get($data->payload, 'accountNumber');
        $this->accountCode = Arr::get($data->payload, 'accountCode');
        $this->entity = $data->entity;
        $this->payload = (array)$data;
    }
}
