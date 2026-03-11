<?php

namespace App\Http\Actions\Payments\Saana\Trait;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait HandleRequest
{
    public function sendRequest($dto): \Illuminate\Http\Client\Response
    {

        Log::channel('saanapay')->info('NGN Cashout PAYLOAD',[$dto->payload]);
        return  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->post("{$this->baseUrl}/sendmoney", $dto->payload);

    }

    /**
     * @throws \JsonException
     */
    public function buildResponse(string $response): array
    {
        try {

        Log::channel('saanapay')->info('NGN Cashout Response',[$response]);

        $body = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        gettype(Arr::get($body, 'data'));
        $status = match (Arr::get($body, 'status')) {
            true => 'pending',
            default => 'failed',
        };

        $code = $status == 'pending' ? 200 : 422;



        }catch (\Exception $e){
            $status = 'failed';
            $code = '422';
            $body = [];
        }

        return [
            'status' => $status,
            'statusCode'=> $code,
            'httpCode'=> $code,
            'description' => Arr::get($body, 'data.message'),
            'errors' => Arr::get($body, 'data.errors'),
        ];
    }


    /**
     * @throws \JsonException
     */
    public function processRequest($dto): array
    {
        $rawResponse = $this->sendRequest($dto)->body();
        $response = $this->buildResponse($rawResponse);
        return [$response, $rawResponse];
    }
    
}
