<?php

namespace App\Http\Actions\Wallet;

use App\Http\Actions\ActionInterface;
use App\Models\Wallet;
use App\Models\WemaTemporaryAccount;
use Illuminate\Support\Facades\Auth;

class GenerateWemaVirtualAccount implements ActionInterface
{
    public function execute()
    {
        if (request()->phone) {
            $phone = request()->phone;
        } else {
            $phone = Auth::user()->phone;
        }
        $wema_prefix = env('WEMA_PREFIX', '731');
        $expected_wema_account = $wema_prefix.substr($phone, -7);
        while ($this->accountExistsInWemaNgnAccounts($expected_wema_account)) {
            $expected_wema_account = $wema_prefix.$this->getRandomAccount();
        }

        return $expected_wema_account;
    }

    private function getRandomAccount()
    {
        $randomAccount = mt_rand(1000000, 9999999);

        return $randomAccount;
    }

    private function accountExistsInWemaNgnAccounts($accountNumber): bool
    {
        $wemaNgnAccount = Wallet::whereWemaNgnAccount($accountNumber)->first();
        $wemaTemporaryAccount = WemaTemporaryAccount::whereAccountNumber($accountNumber)->whereNull('expired_at')->first();
        if ($wemaNgnAccount || $wemaTemporaryAccount) {
            return true;
        }

        return false;
    }
}
