<?php

namespace App\Http\Actions\Traits;

use App\Exceptions\ChannelNotSupportedException;
use App\Models\CashOutMethod;
use App\Models\ExternalPaymentProvider;
use App\Repositories\CashOutMethodRepository;

trait GetsCashoutMethod
{
    /**
     * @return CashOutMethod|\Illuminate\Database\Eloquent\Model|object|null
     *
     * @throws \App\Exceptions\CustomModelNotFoundException
     * @throws \Throwable
     */
    public function getCashOutMethod($provider)
    {
        $provider = ExternalPaymentProvider::whereName($provider)->first();

        $method = CashOutMethodRepository::find(request()->cash_out_method_id);

        throw_if(
            ! $method->externalpaymentproviders->contains($provider),
            new ChannelNotSupportedException
        );

        return $method;
    }
}
