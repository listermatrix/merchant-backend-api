<?php
namespace App\Http\ControllerTraits;

use App\Models\Wallet;
use App\Models\WalletServiceDeactivation;
use App\Exceptions\CustomBadRequestException;

trait DisableWalletServiceTrait
{
    public function disableWalletServices(Wallet $wallet, string $serviceName)
    {
        $walletServiceDeactivation = WalletServiceDeactivation::where('wallet_id', $wallet->id)
                                                                ->where('service_name', $serviceName)
                                                                ->first();

        throw_if(
            $walletServiceDeactivation,
            new CustomBadRequestException('The account service is currently unavailable')
        );
    }
}
