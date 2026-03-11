<?php

namespace Domain\BXBank\MoneyTransfer\Transact\Dtos;

use App\Models\BrijxIntentFulfillmentRequest;
use App\Models\Wallet;
use App\Models\CashOutMethod;
use App\Models\BrijXServiceTransaction;
use App\Models\MoniesHeldForSwapTransfer;
use App\Models\RemittanceDestinationRail;
use Spatie\DataTransferObject\DataTransferObject;

class RemittanceData extends DataTransferObject
{
    public MoniesHeldForSwapTransfer $moneyHeldForSwap; 

    public BrijXServiceTransaction $serviceTransaction;

    public RemittanceDestinationRail $railConfig;

    public CashOutMethod $cashOutMethod;

    public Wallet $wallet;

    public BrijxIntentFulfillmentRequest $intent;

    public static function toDTO(array $data)
    {
        $cashOutMethod = CashOutMethod::whereId($data['railConfig']->cash_out_method_id)->first(); 

        $data['cashOutMethod'] = $cashOutMethod; 

        return new self($data);
    }
}
