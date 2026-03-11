<?php

namespace App\Helpers\NameResolution;

use App\Models\User;
use App\Models\SettlementAccount;
use App\Helpers\NameResolution\Strategies\Source;
use App\Http\Resources\BulkPaymentTransactionResource;

class DefaultNameResolution extends Source
{
    public function canHandlePayload(): bool
    {
        return true;
    }

    public function handle()
    {
        $user = $this->entity->user ?? $this->entity;

        $phone = $user->phone;
        $message = "Hi {$user->firstname}, name validation failed for {$this->accountId} failed. To be able to use it you must verify it by uploading proof of account ownership. Thanks";
        sendsms($phone, $message);
    }
}
