<?php

namespace App\Http\Actions\Payments\Saana\Sources;

use Illuminate\Support\Arr;

abstract class ResponseHandler
{
    public string $status;

    public ?string $statusDescription;

    public mixed $rawResponse;

    public ?array $response;

    public object $dto;

    public function __construct(array $data)
    {
        $this->status = Arr::get($data, 'status');
        $this->response = Arr::get($data, 'response');
        $this->statusDescription = Arr::get($data,'statusDescription');
        $this->rawResponse = Arr::get($data,'rawResponse');
        $this->dto = Arr::get($data,'dto');
    }

    abstract public function canHandlePayload(): bool;

    abstract public function handle();
}
