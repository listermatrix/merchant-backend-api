<?php

namespace App\Http\ControllerTraits\WalletFund;

use App\Http\Actions\Auth\GenerateOTP;
use App\Http\Requests\SendMomoOTPRequest;
use Illuminate\Support\Facades\Auth;

trait SendsOtp
{
    public function sendOTP(SendMomoOTPRequest $request, GenerateOTP $action)
    {
        //send otp to user
        $user = Auth::user();
        $phone =  $user?->phone;

        if ($user && !blank($phone) ) {
            $action->execute($user, $phone);

            return $this->respond([
                'status' => 200,
                'data' => null,
                'message' => 'OTP generated successfully and sent to user. OTP expires in '.OTP_EXPIRES_IN.' seconds',
            ]);
        }
    }
}
