<?php

namespace App\Http\Actions\Auth;

use App\Models\UserOTP;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class MakeOTP
{
    public function execute($user): int
    {
        $generated_otp = makeOTP();

        $authorType = get_class($user);
        $authorId = $user->id;

        $otp = UserOTP::updateOrCreate(['author_type' => $authorType, 'author_id' => $authorId],
            [
                'otp' => Hash::make($generated_otp),
                'author_type'=> $authorType,
                'author_id' => $authorId,
                'expires_at' => Carbon::now()->addSeconds(OTP_EXPIRES_IN),
            ]);

        return $generated_otp;
    }
}
