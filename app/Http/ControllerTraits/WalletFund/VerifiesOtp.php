<?php

namespace App\Http\ControllerTraits\WalletFund;

use App\Exceptions\CustomBadRequestException;
use App\Helpers\Auth\VerifyOTP;
use App\Models\UserOTP;
use Illuminate\Support\Facades\Auth;

trait VerifiesOtp
{
    public function verifyOtp()
    {
        $user = Auth::user();

        $authorType = get_class($user);
        $authorId = $user->id;

        $userOtp = UserOTP::query()->where(['author_type' => $authorType, 'author_id' => $authorId])->latest()->first();

        throw_if(
            ! $userOtp,
            new CustomBadRequestException('The specified OTP is invalid.')
        );

        $verify = VerifyOTP::passed(request('otp'), $userOtp);

        if (! $verify) {
            throw new CustomBadRequestException('The OTP provided is not valid or has expired');
        }
    }
}
