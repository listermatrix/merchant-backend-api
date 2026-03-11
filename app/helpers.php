<?php

use Carbon\Carbon;
use App\Types\Money;
use App\Models\Client;
use App\Models\Wallet;
use App\Models\Country;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\WalletTransaction;
use App\Exceptions\CustomModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;



if (!function_exists('legacyCurrency')) {
    function legacyCurrency($currency)
    {
        $map = [
            'KES' => 'KSH',
            'GHS' => 'GHS',
            'NGN' => 'NGN',
            'SLE' => 'SLL',
        ];

        return Arr::get($map, $currency) ?? $currency;
    }
}

// Returns money to 2dp without round up
if (!function_exists('normalizeFloat')) {
    function normalizeFloat($number, $places = 2): float|int
    {
        $power = pow(10, $places);
        if ($number > 0) {
            return floor($number * $power) / $power;
        }

        return ceil($number * $power) / $power;
    }
}

if (! function_exists('makeMoney')) {
    function makeMoney(float $value): ?Money
    {
        return new Money($value);
    }
}

if (! function_exists('removeplus')) {
    function removeplus($phone): array|string|null
    {
        return removePlusFromPhoneNo($phone);
    }
}

if (!function_exists('makeStringMoney')) {
    function makeStringMoney($amount)
    {
        return (new Converters())->convertDecimalToStringMoney(totwodp($amount));
    }
}

if (! function_exists('makePayoutTransactionId')) {
    function makePayoutTransactionId(): string
    {
        return date('Ymd') . makeGenericId();
    }
}

function makeReceiptNumber()
{
    $memory = Configuration::where('name', 'last_receipt_number')->first();

    $next_id = (int) $memory->value + 1;

    $memory->value = $next_id;
    $memory->save();

    return prepareStringInteger($next_id, 6);
}

if (!function_exists('makeGenericId')) {
    function makeGenericId($length = 12)
    {
        return makeTellerId($length);
    }
}
if (!function_exists('makeTellerId')) {
    function makeTellerId($length = 12): int
    {
        $map = [
            8 => [
                'start' => 11111111,
                'end' => 99999999,
            ],
            11 => [
                'start' => 11111111111,
                'end' => 99999999999,
            ],
            12 => [
                'start' => 111111111111,
                'end' => 999999999999,
            ],
        ];

        return mt_rand(Arr::get($map, "{$length}.start"), Arr::get($map, "{$length}.end"));
    }
}

if (!function_exists('hasExpired')) {
    function hasExpired($token): bool
    {
        $expires_at = Carbon::createFromFormat('Y-m-d H:i:s', $token->expires_at);
        $now = Carbon::now();

        if ($now->greaterThan($expires_at)) {
            return true;
        }

        return false;
    }
}
if (!function_exists('makeReceiptNumber')) {
    function makeReceiptNumber()
    {
        $memory = Configuration::where('name', 'last_receipt_number')->first();

        $next_id = (int) $memory->value + 1;

        $memory->value = $next_id;
        $memory->save();

        return prepareStringInteger($next_id, 6);
    }
}



if (!function_exists('logTransaction')) {
    /** Logs the transaction */
    function logTransaction(array $data)
    {
        return WalletTransaction::create($data);
    }
}
if (!function_exists('makeTransactionId')) {
    function makeTransactionId()
    {
        $memory = Configuration::where('name', 'last_transaction_number')->first();

        $next_id = (int) $memory->value + 1;

        $memory->value = $next_id;
        $memory->save();

        return prepareStringInteger($next_id, 12);
    }
}
if (!function_exists('prepareStringInteger')) {
    function prepareStringInteger($integer, $length)
    {
        $tmp = '';
        $numlength = strlen((string) $integer);

        for ($i = 1; $i <= $length - $numlength; ++$i) {
            $tmp .= '0';
        }

        return $tmp . (string) $integer;
    }
}
if (!function_exists('getClientFromAuth')) {
    function getClientFromAuth()
    {
        $clients_uuid = Auth::user()->client->uuid;

        $client = Client::whereUuid($clients_uuid)->first();

        if (blank($client)) {
            throw new CustomModelNotFoundException(
                "No client found with the id {$clients_uuid}"
            );
        }

        return $client;
    }
}
if (!function_exists('sendsms')) {

    /**
     * It sends sms messages
     * to phone number
     */
    function sendsms(string $to, string $message): bool
    {
        if (handleSmsOnDev($to, $message)) {
            return true;
        }

        dispatch(new SendSMSJob($to, $message))->onQueue('sms');

        return true;
    }
}



/* generates a random int val used as OTP */

function makeOTP()
{
    return mt_rand(100000, 999999);
}


if (! function_exists('sendPushNotification')) {
    /**
     * Send Push Notification
     *
     * @param  $device_token
     * @return string
     */
    function sendPushNotification($user_id, $title, $message)
    {
        $client_fcm_subscription = \App\Models\FirebasePushMessaging::query()
            ->where('user_id', $user_id)
            ->first();

        // TODO: refactor to make it readable
        if (
            ! blank($client_fcm_subscription) &&
            $client_fcm_subscription->subscription_status != 'unsubscribed'
        ) {
            $device_token = \App\Models\FirebasePushMessaging::query()
                ->where('user_id', $user_id)
                ->first();
            $notification = [
                'title' => $title,
                'body' => $message,
            ];

            dispatch(
                new PushNotificationToMobileJob($device_token, $notification)
            );
        }

        return 'ok';

        // (new PushFCM)->execute($device_token, $notification);
    }
}

if (! function_exists('logMerchantReceipt')) {
    /** Logs the merchant receipt */
    function logMerchantReceipt(array $data)
    {
        return \App\Models\MerchantReceipt::create($data);
    }
}

if (! function_exists('vetTrxId')) {
    /**
     * Generate alphanumeric string
     *
     * @return string
     */
    function vetTrxId($trx_string)
    {
        $chk_existing = WalletTransaction::query()
            ->where('transaction_id', $trx_string)
            ->first();
        if ($chk_existing) {
            return true;
        } else {
            return false;
        }
    }
}


if (! function_exists('debitWallet')) {
    function debitWallet($wallet, $amount)
    {
        $wallet->balance -= $amount;
        $wallet->debit -= $amount;
        $wallet->save();

        return $wallet->refresh();
    }
}

if (! function_exists('creditWallet')) {
    function creditWallet($wallet, $amount)
    {
        $wallet->balance += $amount;
        $wallet->credit += $amount;
        $wallet->save();

        return $wallet->refresh();
    }
}

if (! function_exists('callbackIsSet')) {
    function callbackIsSet($merchant)
    {
        if (blank($merchant->configuration?->callback_url)) {
            return false;
        }

        return true;
    }
}

if (! function_exists('removePlusAndCountryCode')) {
    function removePlusAndCountryCode($msisdn)
    {
        return preg_replace("/^\+?(234|254|233)/", '', $msisdn);
    }
}

if (! function_exists('getWalletTransactionRemark')) {
    function getWalletTransactionRemark(string $remark)
    {
        return match ($remark) {
            'data' => 'Data',
            'fund' => 'Fund',
            'sent' => 'Sent',
            'payout' => 'Payout',
            'cashout' => 'CashOut',
            'invoice' => 'Invoice',
            'paybill' => 'PayBill',
            'airtime' => 'Airtime',
            'voucher' => 'Voucher',
            'request' => 'Request',
            'buygoods' => 'BuyGoods',
            'reversal' => 'Reversal',
            'billpaid' => 'BillPaid',
            'received' => 'Received',
            'sendmoney' => 'SendMoney',
            'brijxoffer' => 'BrijxOffer',
            'payproduct' => 'PayProduct',
            'remittance' => 'Remittance',
            'paywithbrij' => 'PayWithBrij',
            'paymentlink' => 'PaymentLink',
            'billpayment' => 'BillPayment',
            'paymerchant' => 'Paymerchant',
            'brijx_escrow' => 'BrijxEscrow',
            'paidmerchant' => 'PaidMerchant',
            'paid_with_brij' => 'PaidWithBrij',
            'fund_brij_user' => 'FundBrijUser',
            'brijxoffersell' => 'BrijxOfferSell',
            'paymentcampaign' => 'PaymentCampaign',
            'receivedpayment' => 'ReceivedPayment',
            'airtime_purchase' => 'AirTimePurchase',
            'merchant-payment' => 'MerchantPayment',
            'brijxofferclosed' => 'BrijxOfferClosed',
            'expiredbrijxoffer' => 'ExpiredBrijxOffer',
            'received_with_brij' => 'ReceivedWithBrij',
            'purchasebrijxoffer' => 'PurchaseBrijOffer',
            'brijxofferpurchase' => 'BrijxOfferPurchase',
            'paymentlinktemplate' => 'PaymentLinkTemplate',
            'sent_payment_to_mobile' => 'SentPaymentToMobile',
            'qrcode' => 'QRCode',
            'fund-collection' => 'Fund-Collection',
            default => null,
        };
    }
}

if (! function_exists('getRemark')) {
    function getRemark($meta): string
    {
        $remark = 'paymentlink';

        if (Arr::get($meta, 'payment_type') == 'invoice') {
            $remark = 'invoice';
        } elseif (Arr::get($meta, 'payment_type') == 'paymentcampaign') {
            $remark = 'paymentcampaign';
        } elseif (Arr::get($meta, 'payment_type') == 'paymentlinktemplate') {
            $remark = 'paymentlinktemplate';
        } elseif (Arr::get($meta, 'payment_type') == 'qrcode') {
            $remark = 'qrcode';
        }

        return $remark;
    }
}

if (! function_exists('formatAmountWithCommas')) {
    function formatAmountWithCommas($amount): string
    {
        return number_format($amount, 2, '.', ',');
    }
}

if (! function_exists(('logInvoicePayment'))) {
    function logInvoicePayment($fund, $meta, $momoNumber): void
    {
        if (Arr::get($meta, 'payment_type') == 'invoice') {
            $invoice = Invoice::whereUuid(request('meta.payment_type_id'))->first();
            InvoicePaymentLog::create([
                'wallet_transaction_id' => $fund->id,
                'transaction_id' => $fund->transaction_id,
                'invoice_id' => $invoice->id,
                'status' => 'pending',
                'currency' => $invoice->currency,
                'amount' => $invoice->total,
                'customer_phone' => $momoNumber,
            ]);
        }
    }
}

if (! function_exists('getAuthor')) {
    function getAuthor(string $authorType, int|string $authorId)
    {
        $model = getModel($authorType);

        if (!$model) {
            return null;
        }

        $model = $model::where('id', $authorId)->first();

        if (!$model) {
            return null;
        }

        return [
            'id' => $model?->uuid,
            'firstname' => $model?->firstname ?? $model?->first_name,
            'lastname' => $model?->lastname ?? $model?->last_name,
            'phone' => $model->phone,
            'email' => $model->email,
        ];
    }
}

if (! function_exists('getModel')) {
    function getModel($authorType)
    {
        return match ($authorType) {
            'App\Models\User' => User::class,
            'App\Models\SubUser' => SubUser::class,
            'App\Models\AdminUser' => AdminUser::class,
            default => null,
        };
    }
}

if (!function_exists('sendMail')) {
    function sendMail(
        string $to,
        string $message,
        string $recipient = null,
        string $subject = null,
        string $from = null,
        string $sender = null
    ): void {
        $subject ??= 'Brij Notification';
        $from ??= config('orobopay.general.email');
        $sender ??= config('orobopay.general.from');

        $mail = new NotifyMail(
            $to,
            $message,
            $recipient,
            $subject,
            $from,
            $sender
        );

        Mail::send($mail);
    }
}

if (!function_exists('generateGeneralTicketNumber')) {
    function generateGeneralTicketNumber($ticketId): string
    {
        $digit = str_pad($ticketId, 4, '0', STR_PAD_LEFT);

        return "BRIJTSPT{$digit}";
    }
}

if (!function_exists('getBrijUserAccountName')) {

    function getBrijUserAccountName(string $walletId): ?string
    {
        $wallet = Wallet::whereUuid($walletId)->whereStatus('active')->first();

        if ($wallet) {
            $user = User::where('client_id', $wallet->client_id)->first();

            if ($user && $user->is_merchant == 'no') {
                return $user?->fullname;
            }

            if ($user && $user->is_merchant == 'yes') {
                return $user->client?->business_name;
            }
            return null;
        }

        return null;
    }
}


if (! function_exists(('maskCardNumber'))) {
    function maskCardNumber(string $cardNumber): string
    {
        // Ensure the card number is a string
        $cardNumber = (string) $cardNumber;

        // Mask all but the last 4 digits
        return Str::mask($cardNumber, '*', 0, -4);
    }
}

if (! function_exists('resolveCurrencyToCountryCurrency')) {
    function resolveCurrencyToCountryCurrency(string $currency): ?string
    {
        return Country::where('currency_symbol', $currency)->first()?->country_currency;
    }
}

if (! function_exists('exceptionLogger')) {
    function exceptionLogger(Exception $exception): ?array
    {
        return [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'message' => $exception->getMessage(),
        ];
    }
}

if (!function_exists('fetchRateFromApi')) {
    /**
     * Helper function to fetch rate from API for USD to a given currency
     * @throws RequestException
     */
    function fetchRateFromApi(string $currency): float
    {
        $cacheKey = "exchange_rate_usd_to_{$currency}";
        $cacheTTL = 3600; // 1 hour in seconds

        // Attempt to get cached rate
        $rate = Cache::get($cacheKey);

        // If the cached rate is 0 or null, force a fresh API call
        if ($rate === null || $rate === 0) {
            $baseUrl = config('services.brijx_exchange_rate.base_url');

            $response = Http::baseUrl($baseUrl)
                ->get('api/v2/brijxthirdparty/forex/usd-rates', ['to' => $currency])
                ->throw()
                ->json();

            $rate = $response['rate'] ?? 0;

            // Store the new rate in cache, even if it's 0 (to avoid repeated API calls)
            Cache::put($cacheKey, $rate, $cacheTTL);
        }

        return $rate;
    }

    if (! function_exists('collectionRemarks')) {
        function collectionRemarks(): array
        {
            return  ['paymentlink', 'invoice', 'paymentcampaign', 'paymentlinktemplate', 'qrcode'];
        }
    }
}
