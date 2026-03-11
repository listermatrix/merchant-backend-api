<?php

namespace App\Helpers\NameResolution;


use App\Http\Actions\Payments\Saana\SaanaBankProcessor;
use Illuminate\Support\Arr;
use App\Helpers\NameResolution\Strategies\Source;
use Illuminate\Support\Facades\Log;

class SaanaPayNgNameResolution extends Source
{
    public function canHandlePayload(): bool
    {
        return $this->countryCode == 'NG' && $this->processor ='saanapay';
    }

    public function handle(): string
    {
        $cashoutMethod = $this->payload['payload']['cashoutMethod'];
        $payload = [
            "bank_code" => $cashoutMethod->code,
            "bank" => $cashoutMethod->name,
            "account_number" => $this->payload['payload']['accountNumber']
        ];

        $data =  (new SaanaBankProcessor())->getAccountDetails($payload);
        Log::channel('saanapay')->info('NGN Cashout Name Resoln Response',[$data]);
        return Arr::get($data, 'data.account_name');
    }


}
