<?php

namespace App\Http\Actions\Payments\Saana;

use App\Exceptions\CustomBadRequestException;
use App\Exceptions\CustomModelNotFoundException;
use Illuminate\Support\Facades\Log;
use stdClass;
use Illuminate\Support\Arr;
use App\Models\CashOutMethod;
use App\Http\Actions\Traits\GetsCashoutMethod;
use App\Http\ControllerTraits\DisablesCashoutAction;
use App\Http\ControllerTraits\WalletFund\VerifiesOtp;
use App\Http\Actions\Payments\Contracts\CashOutAction;

class SaanaPayCashOutAction implements CashOutAction
{
    use DisablesCashoutAction, GetsCashoutMethod, VerifiesOtp;

    /**
     * @throws CustomModelNotFoundException
     * @throws \Throwable
     */
    public function buildPayload(array $data = []): array
    {
        return (new SaanaPayCashoutPayload())->getPayload(
            $this->getCashOutMethod('SaanaPAY')
        );
    }

    /**
     * @throws CustomModelNotFoundException
     * @throws \Throwable
     */
    public function execute(): array
    {
         $clientId = auth()->user()->client->merchant_id;

        throw_if(blank(request()->otp), new CustomBadRequestException('OTP is required for this action'));

        $this->verifyOtp();

        //returns cashout mtd tied to saanaPay as ext provider
        $cashOutMethod = $this->getCashOutMethod('SaanaPAY');

        $payload = $this->buildPayload();

        $dto = $this->makePayloadDTO($cashOutMethod, $payload);
        Log::channel('saanapay')->info('NGN Cashout Request',[$payload]);

        return SaanaProcessorFactory::getProcessor($cashOutMethod)->process($dto);

    }

    private function makePayloadDTO(CashOutMethod $cashOutMethod, array $payload): stdClass
    {
        $dto = new stdClass;

        $dto->accountNumber = Arr::get($payload, 'account_number');
        $dto->bankCode = Arr::get($payload, 'bank_code');
        $dto->bankName = Arr::get($payload, 'bank');
        $dto->transactionId = (string) Arr::get($payload, 'request_id');
        $dto->amount = request('amount');
        $dto->totalAmount = request('amount');
        $dto->payload = $payload;
        $dto->cashOutMethod = $cashOutMethod;
        $dto->feeCode = $cashOutMethod->charge_code;

        return $dto;
    }



}
