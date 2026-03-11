<?php

namespace App\Http\Actions\Payments\Saana;

use App\Exceptions\CustomBadRequestException;
use App\Exceptions\DataFailedValidationException;
use App\Http\Actions\Payments\Contracts\CashoutPayload;
use App\Models\CashOutMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class SaanaPayCashoutPayload implements CashoutPayload
{

    /**
     * @throws DataFailedValidationException
     */
    public function getPayload(CashOutMethod $cashout): array
    {
        $this->validate();

        $requestId = (string) makeTellerId();
        $cashoutMethod = $this->getCashoutMethod();
        return [
            'bank_code' => $cashoutMethod->code,
            'bank' => $cashoutMethod->name,
            'account_number'=>request('bank_account_number'),
            'amount'=>request('amount'),
            'request_id'=>$requestId
        ];
    }


    /**
     * @throws DataFailedValidationException
     */
    private function validate(): void
    {
        $validator = Validator::make(request()->all(), [
            'cash_out_method_id' => ['required'],
            'bank_account_number' => ['required'],
        ]);

        if ($validator->fails()) {
            throw new DataFailedValidationException($validator->errors());
        }

    }


    private function getCashoutMethod(): Model|Builder|null
    {
        return CashOutMethod::whereUuid(request('cash_out_method_id'))->first();
    }
}
