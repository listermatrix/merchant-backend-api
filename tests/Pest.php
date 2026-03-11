<?php

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use Hashids\Hashids;
use App\Models\Client;
use App\Models\Wallet;
use App\Models\Country;
use App\Models\Permission;
use App\Models\WalletType;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\MerchantType;
use Laravel\Sanctum\Sanctum;
use App\Models\CashOutMethod;
use App\Models\BraasTransaction;
use App\Models\MerchantIndustry;
use App\Models\ClientAccountType;
use App\Models\CountryWalletType;
use App\Models\WalletTransaction;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;
use App\Models\BrijXServiceTransaction;
use App\Models\ExternalPaymentProvider;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\CellulantWebhookInterceptJob;
use Spatie\WebhookClient\Models\WebhookCall;
use App\Models\ExternalPaymentProviderCashInMethod;
use App\Models\ExternalPaymentProviderCashOutMethod;
use App\Http\Actions\Helpers\CreatePersonalAccessToken;
use App\Models\GMoneyWallet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use App\Models\BrijxRemittanceTrxConfig;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
 */

uses(TestCase::class, LazilyRefreshDatabase::class)->beforeEach(function() {
    ray()->newScreen();
})->in(__DIR__);



define('BRIJ_REQUEST_ID', Str::uuid());

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
 */

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
 */

function createBrijxRemittanceTrxConfig(array $overrides = []): BrijxRemittanceTrxConfig
{
    return BrijxRemittanceTrxConfig::create(array_merge([
        'percentage_rate_markup' => 0.2,
        'percentage_fee' => 1.05,
        'source_currency' => 'NGN',
        'destination_currency' => 'KSH',
        'fee_cap' => 952450,
        'minimum_fee' => 2500,
        'maximum_fee' => 10000,
        'maximum_amount_per_transaction' => 2500000,
        'maximum_transaction_per_day' => 5000000
    ], $overrides));
}

function createBrijxIntentFulfillmentPartnerRole(string $permissionName = 'all')
{
    $brijxIntentFulfillmentPartnerRole = Role::factory()->create([
        'name' => 'brijx-intent-fulfillment-partner',
        'display_name' => 'Brijx Intent Fulfillment Partner',
        'guard_name' => 'api',
        'description' => 'Users with this role can fulfill brijx intents',
    ]);

    $permissions = [
        'brijx:view-intent-fulfillment-requests' => [
            'name' => 'brijx:view-intent-fulfillment-requests',
            'description' => 'Enables merchant to view intent fulfillment requests',
            'permission_category' => 'API Key Permissions',
            'guard_name' => 'api',
        ],
        'brijx:process-intent-fulfillment-requests' => [
            'name' => 'brijx:process-intent-fulfillment-requests',
            'description' => 'Enables merchant to process intent fulfillment requests',
            'permission_category' => 'API Key Permissions',
            'guard_name' => 'api',
        ],
        'brijx:reject-intent-fulfillment-requests' => [
            'name' => 'brijx:reject-intent-fulfillment-requests',
            'description' => 'Enables merchant to reject intent fulfillment requests',
            'permission_category' => 'API Key Permissions',
            'guard_name' => 'api',
        ],
    ];

    if ($permissionName == 'all') {
        collect($permissions)->each(function ($record, $key) use ($brijxIntentFulfillmentPartnerRole) {
            $permission = Permission::create($record);

            if ($permission->wasRecentlyCreated) {
                $brijxIntentFulfillmentPartnerRole->givePermissionTo($permission);
            }
        });
    } else {
        $permission = $permission = Permission::create(Arr::get($permissions, $permissionName));

        if ($permission) {
            $brijxIntentFulfillmentPartnerRole->givePermissionTo($permission);
        }
    }

    return $brijxIntentFulfillmentPartnerRole;
}


function runMigrations($override = [])
{
    $migrations = array_merge($override, [

    ]);

    collect($migrations)->each(function($file){
        Artisan::call('migrate', [
            '--path' => '/database/migrations/'.$file
        ]);
    });
}

 function createPersonalAccessToken($user)
 {
    $token = (new CreatePersonalAccessToken)->execute($user, 'master-token');

    $access =  PersonalAccessToken::where('tokenable_id', $user->id)
        ->where('name', 'master-token')
        ->first();

    $access->plain_text_token = $token->plainTextToken;
    $access->expires_at = now()->addHours(TOKEN_EXPIRATION_IN_HOURS);

    $access->save();

    return $access->refresh();
 }

function actAsBasicAuth($user)
{
    app('auth')->guard('web')->setUser($user);
    app('auth')->shouldUse('web');
}

function makeBankTrxCommitResponse(
    $statusCode = 200,
    $receivingPartnerId = null,
    $statusMessage = null,
    $mfsTransId = null,
    $brijTransId = null
) {
    return "<?xml version='1.0' encoding='UTF-8'?>
            <soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/'>
               <soapenv:Body>
                  <ns:trans_comResponse xmlns:ns='http://ws.mfsafrica.com'>
                     <ns:return xmlns:ax21='http://mfs/xsd' xmlns:ax23='http://airtime/xsd' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:type='ax21:EResponse'>
                        <ax21:code xsi:type='ax21:code'>
                           <ax21:status_code>$statusCode</ax21:status_code>
                        </ax21:code>
                        <ax21:e_trans_id>$receivingPartnerId</ax21:e_trans_id>
                        <ax21:message>$statusMessage</ax21:message>
                        <ax21:mfs_trans_id>$mfsTransId</ax21:mfs_trans_id>
                        <ax21:third_party_trans_id>$brijTransId</ax21:third_party_trans_id>
                     </ns:return>
                  </ns:trans_comResponse>
               </soapenv:Body>
            </soapenv:Envelope>";
}

function makeBankTrxLogResponse(
    $amount = null,
    $currency = null,
    $brijTransactionId = null,
) {
    return '<?xml version="1.0" encoding="utf-8"?>
    <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
       <ns:bank_trans_logResponse xmlns:ns="http://ws.mfsafrica.com">
          <ns:return xsi:type="ax21:TransactionBank" xmlns:ax21="http://mfs/xsd" xmlns:ax23="http://airtime/xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
             <ax21:fx_rate>1.01</ax21:fx_rate>
             <ax21:mfs_trans_id>1001920192</ax21:mfs_trans_id>
             <ax21:e_trans_id>2019230</ax21:e_trans_id>
             <ax21:receive_amount xsi:type="ax21:Money">
                <ax21:amount>'.$amount.'</ax21:amount>
                <ax21:currency_code/>
             </ax21:receive_amount>
             <ax21:send_amount xsi:type="ax21:Money">
                <ax21:amount>'.$amount.'</ax21:amount>
                <ax21:currency_code>'.$currency.'</ax21:currency_code>
             </ax21:send_amount>
             <ax21:third_party_trans_id>'.$brijTransactionId.'</ax21:third_party_trans_id>
             <ax21:status>
                <ax21:code>MR101</ax21:code>
                <ax21:message>Transaction processed</ax21:message>
            </ax21:status>
          </ns:return>
       </ns:bank_trans_logResponse>
    </soapenv:Body>
 </soapenv:Envelope>';
}

function makeBankValidationResponse(
    $accountName = 'Jack Alawa',
    $accountNumber = '109990000293',
    $statusCode = 'Active',
    $partnerCode = '569',
) {
    return "<?xml version='1.0' encoding='UTF-8'?>
        <soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/'>
            <soapenv:Body>
                <ns:validate_bank_accountResponse xmlns:ns='http://ws.mfsafrica.com'>
                    <ns:return xsi:type='ax21:BankAccount' xmlns:ax21='http://mfs/xsd' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'>
                        <ax21:account_holder_name>$accountName</ax21:account_holder_name>
                        <ax21:account_number>$accountNumber</ax21:account_number>
                        <ax21:mfs_bank_code>$partnerCode</ax21:mfs_bank_code>
                        <ax21:status xsi:type='ax21:Code'>
                            <ax21:status_code>$statusCode</ax21:status_code>
                        </ax21:status>
                    </ns:return>
                </ns:validate_bank_accountResponse>
            </soapenv:Body>
        </soapenv:Envelope>";
}
function makeHashId($id, $salt, $length = 10)
{
    $hashId = new Hashids($salt, $length);

    $id = $hashId->encode($id);

    return $id;
}

function manuallySimulateCellulantCheckoutStatusAcknowledgementResponse($overrides = [])
{
    return [
        'status' => [
            'statusCode' => 200,
            'statusDescription' => 'Success',
        ],
    ];
}

function manuallySimulateCellulantCheckoutRequeryResponse($overrides = [])
{
    return [
        'status' => [
            'statusCode' => 200,
            'statusDescription' => 'Successfully processed request',
        ],
        'results' => [
            'checkoutRequestID' => 70659444,
            'merchantTransactionID' => '221849893540',
            'MSISDN' => 233550544075,
            'accountNumber' => '233550544075',
            'requestDate' => '2023-11-07 13:57:34',
            'requestStatusCode' => 178,
            'serviceName' => 'BRIJ FINTECH GHANA LTD',
            'serviceCode' => 'BRIJFINTECHGHANA_GHA',
            'requestCurrencyCode' => 'GHS',
            'requestAmount' => 30,
            'paymentCurrencyCode' => 'GHS',
            'amountPaid' => 30,
            'shortUrl' => '',
            'redirectTrigger' => '',
            'session' => 0,
            'redirectURL' => '',
            'payments' => [
                [
                    'payerTransactionID' => '30390818113',
                    'MSISDN' => 233550544075,
                    'accountNumber' => '233550544075',
                    'customerName' => 'Customer',
                    'amountPaid' => 30,
                    'payerClientCode' => 'MTNGH',
                    'cpgTransactionID' => '1906377775',
                    'paymentDate' => '2023-11-07 13:58:13',
                    'clientName' => 'MTN MTNGH',
                    'clientDisplayName' => 'MTN',
                    'currencyCode' => 'GHS',
                    'currencyID' => 50,
                    'paymentID' => 48906525,
                    'hubOverallStatus' => 139,
                    'clientCategoryID' => 1,
                    'clientCategoryName' => 'Mobile Money',
                    'payerNarration' => 'SUCCESSFUL',
                ],
            ],
            'failedPayments' => [],
            'rejectedPayments' => [],
            'paymentInstructions' => '',
            'offline' => true,
            'customerNationalID' => '',
            'customerPassportNumber' => '',
        ],
    ];
}

function manuallySimulateCellulantCheckoutResponse($overrides = [])
{
    return [
        'results' => [
            'checkoutRequestID' => 70656466,
            'merchantTransactionID' => '822481594210',
            'conversionRate' => 1,
            'originalCurrencyCode' => 'GHS',
            'requestAmount' => 5,
            'convertedCurrencyCode' => 'GHS',
            'convertedAmount' => 5,
            'paymentOptions' => [
                [
                    'paymentModeName' => 'Push with O.T.P',
                    'paymentModeID' => 23,
                    'serviceCode' => 'BRIJFINTECHGHANA_GHA',
                    'payerClientName' => 'Vodafone Ghana',
                    'payerModeID' => 148,
                    'paymentOptionCode' => 'Mobile Money',
                    'payerClientCode' => 'VODAGH',
                    'countryCode' => 'GH',
                    'clientLogo' => 'Vodafone_Ghana_14-Jun-2018_1528963223.png',
                    'serviceName' => 'BRIJ FINTECH GHANA LTD',
                    'minChargeAmount' => 1,
                    'maxChargeAmount' => 100000,
                    'currencyCode' => 'GHS',
                    'paymentInstructions' => 'You will receive a prompt on your mobile phone ^CHARGE_MSISDN^ to approve your payment of GHS ^CHARGE_AMOUNT^',
                    'languageCode' => 'en',
                ],
                [
                    'paymentModeName' => 'STK Push',
                    'paymentModeID' => 9,
                    'serviceCode' => 'BRIJFINTECHGHANA_GHA',
                    'payerClientName' => 'Airtel Ghana',
                    'payerModeID' => 155,
                    'paymentOptionCode' => 'Mobile Money',
                    'payerClientCode' => 'AIRTELGH',
                    'countryCode' => 'GH',
                    'clientLogo' => 'Tigo_Ghana_14-Jun-2018_1528966202.png',
                    'serviceName' => 'BRIJ FINTECH GHANA LTD',
                    'minChargeAmount' => 1,
                    'maxChargeAmount' => 7000,
                    'currencyCode' => 'GHS',
                    'paymentInstructions' => '<p>You will receive a prompt on your mobile number <b>^CHARGE_MSISDN^</b> to enter your PIN to authorize your payment request of <b>GHS ^CHARGE_AMOUNT^</b> to ^REFERENCE_KEY^ <b>^ACCOUNT_NUMBER^</b>.',
                    'languageCode' => 'en',
                ],
                [
                    'paymentModeName' => 'Push with O.T.P',
                    'paymentModeID' => 23,
                    'serviceCode' => 'BRIJFINTECHGHANA_GHA',
                    'payerClientName' => 'MTN MTNGH',
                    'payerModeID' => 466,
                    'paymentOptionCode' => 'Mobile Money',
                    'payerClientCode' => 'NSANOMOMO',
                    'countryCode' => 'GH',
                    'clientLogo' => 'NSN_MNO_13-Dec-2018_1544707972.jpg',
                    'serviceName' => 'BRIJ FINTECH GHANA LTD',
                    'minChargeAmount' => 1,
                    'maxChargeAmount' => 500000,
                    'currencyCode' => 'GHS',
                    'paymentInstructions' => '<p>You will receive a prompt on your mobile number ^CHARGE_MSISDN^ to enter your PIN to authorize your payment request of GHS ^CHARGE_AMOUNT^ with the ^REFERENCE_KEY^ <b>^REFERENCE^</b>.</p><p>Dial *170# and select option 6 then option 3 to approve the transaction if you do not receive a push prompt</p><p>Please note that mobile money service fees apply and will be added to your payment of GHS ^CHARGE_AMOUNT^ before you authorise your payment.</p>',
                    'languageCode' => 'en',
                ],
            ],
            'chargeResults' => [
                'chargeRequestDate' => '2023-11-07 13:23:35',
                'chargeRequestID' => 54216572,
                'channelRequestID' => '17YMW7W',
                'checkoutRequestID' => 70656466,
                'merchantTransactionID' => '822481594210',
                'chargeAmount' => 5,
                'chargeMsisdn' => '233599871280',
                'paymentInstructions' => '<p>You will receive a prompt on your mobile number 233599871280 to enter your PIN to authorize your payment request of GHS 5.00 with the account number <b>233599871280</b>.</p><p>Dial *170# and select option 6 then option 3 to approve the transaction if you do not receive a push prompt</p><p>Please note that mobile money service fees apply and will be added to your payment of GHS 5.00 before you authorise your payment.</p>',
                'languageCode' => 'en',
                'routingResponse' => null,
                'thirdPartyResponse' => [
                    'authStatus' => ['authStatusCode' => 131, 'authStatusDescription' => 'Authentication was successful.'],
                    'results' => [
                        [
                            'statusCode' => 176,
                            'statusDescription' => 'Charge posted successfully.',
                            'chargeRequestID' => 45984908,
                            'chargeRequestUUID' => '17YMW7W',
                            'paymentReferenceNumber' => '17YMW7W',
                            'paymentCorrelationID' => 45989970,
                            'chargeRequestCorrelationID' => 1906475468,
                            'totalTransactionCharges' => 0.0,
                            'totalChargeBeneficiaries' => 0.0,
                            'totalAmount' => 5.0,
                            'requestReferenceID' => '233599871280',
                        ],
                    ],
                ],
                'paymentRedirectUrl' => null,
            ],
        ],
    ];
}

function manuallySimulateCellulantPayoutWebhookResponse(WalletTransaction $trx, $statusCode = '183')
{
    $webhookCall = WebhookCall::create([
        'name' => 'cellulant',
        'payload' => [
            'function' => 'BEEP.pushPaymentStatus',
            'countryCode' => 'GH',
            'payload' => [
                'packet' => [
                    'statusCode' => $statusCode,
                    'statusDescription' => 'Payment has been accepted by the merchant',
                    'payerTransactionID' => $trx->transaction_id,
                    'beepTransactionID' => 1883728727,
                    'receiptNumber' => '652bec5a8083f852472e38c4',
                    'receiverNarration' => 'Success',
                    'function' => 'POST',
                    'msisdn' => '233544203781',
                    'serviceCode' => 'NSANOMTNB2C',
                    'paymentDate' => '2023-10-15 14:33:00',
                    'clientCode' => 'BRIJFINTECHGHANA_GHA',
                    'extraData' => [
                        'hubID' => 0,
                        'countryCode' => 'GH',
                        'callbackUrl' => 'https://api.brij.money/api/v2/webhooks/cellulant-payout',
                    ],
                    'credentials' => [
                        'username' => null,
                        'password' => 'password',
                    ],
                ],
            ],
        ],
        'exception' => null,
    ]);

    (new CellulantWebhookInterceptJob($webhookCall))->handle();
}

function mimickCellulantPayoutCallback(WalletTransaction $trx, string $statusCode, $testContext)
{
    return $testContext->post('api/v2/webhooks/cellulant', [
        'function' => 'BEEP.pushPaymentStatus',
        'countryCode' => 'GH',
        'payload' => [
            'packet' => [
                'statusCode' => $statusCode,
                'statusDescription' => 'Payment has been accepted by the merchant',
                'payerTransactionID' => $trx->transaction_id,
                'beepTransactionID' => 1883728727,
                'receiptNumber' => '652bec5a8083f852472e38c4',
                'receiverNarration' => 'Success',
                'function' => 'POST',
                'msisdn' => '233544203781',
                'serviceCode' => 'NSANOMTNB2C',
                'paymentDate' => now()->toDateTimeString(),
                'clientCode' => 'BRIJFINTECHGHANA_GHA',
                'extraData' => [
                    'hubID' => 0,
                    'countryCode' => 'GH',
                    'callbackUrl' => 'https://api.brij.money/api/v2/webhooks/cellulant',
                ],
                'credentials' => [
                    'username' => '',
                    'password' => 'password',
                ],
            ],
        ],
    ]);
}

function createPrefundedWallet($balance = 0, $currency = 'GHS')
{
    $financeAccountType = ClientAccountType::whereName('Finance Account')->first();

    if (! $financeAccountType) {
        $financeAccountType = ClientAccountType::factory()->create([
            'name' => 'Finance Account',
        ]);
    }

    $brijFinance = Client::with('user')->whereAccountType($financeAccountType->id)->first()?->user;

    if (! $brijFinance) {
        $brijFinance = createUser(clientOverride: [
            'account_type' => $financeAccountType->id,
        ]);
    }

    $map = [
        'GHS' => 'Cedi',
        'NGN' => 'Naira',
        'KSH' => 'Shilling',
    ];

    $wallettype = WalletType::whereCurrency($currency)->whereIsInternal(1)->whereCategory('prefunds')->first();

    if (! $wallettype) {
        $wallettype = WalletType::factory()->create([
            'name' => 'Brij Prefunded Collections -'.$currency,
            'currency' => $currency,
            'description' => 'Wallet to collect prefunded funds',
            'is_internal' => 1,
            'category' => 'prefunds',
        ]);
    }

    $wallet = Wallet::factory()->create([
        'wallet_type_id' => $wallettype->id,
        'client_id' => $brijFinance->client_id,
        'balance' => $balance,
        'wema_ngn_account' => null,
        'name' => 'Brij prefunds wallet for '.$currency,
    ]);

    safelyCredit($wallet, $balance);

    return $wallet;
}

function makeVisaVerificationResponse()
{
    return [
        'status' => 'success',
        'message' => 'Transaction fetched successfully',
        'data' => [
            'id' => 1163068,
            'tx_ref' => 'akhlm-pstmn-blkchrge-xx6',
            'flw_ref' => 'FLW-M03K-02c21a8095c7e064b8b9714db834080b',
            'device_fingerprint' => 'N/A',
            'amount' => 3000,
            'currency' => 'NGN',
            'charged_amount' => 3000,
            'app_fee' => 1000,
            'merchant_fee' => 0,
            'processor_response' => 'Approved',
            'auth_model' => 'noauth',
            'ip' => 'pstmn',
            'narration' => 'Kendrick Graham',
            'status' => 'successful',
            'payment_type' => 'card',
            'created_at' => '2020-03-11T19 =>22 =>07.000Z',
            'account_id' => 73362,
            'amount_settled' => 2000,
            'card' => [
                'first_6digits' => '553188',
                'last_4digits' => '2950',
                'issuer' => ' CREDIT',
                'country' => 'NIGERIA NG',
                'type' => 'MASTERCARD',
                'token' => 'flw-t1nf-f9b3bf384cd30d6fca42b6df9d27bd2f-m03k',
                'expiry' => '09/22',
            ],
            'customer' => [
                'id' => 252759,
                'name' => 'Kendrick Graham',
                'phone_number' => '0813XXXXXXX',
                'email' => 'user@example.com',
                'created_at' => '2020-01-15T13:26:24.000Z',
            ],
        ],
    ];
}

function makeBrijxPaymentMethodId($id)
{
    $salt = '!BRXBPAY-#PaymentMethods$$$';

    $hashId = new Hashids($salt, 10);

    $paymentMethodId = $hashId->encode($id);

    return $paymentMethodId;
}

function makeVisaRedirectResponse()
{
    return [
        'status' => 'success',
        'message' => 'Charge initiated',
        'data' => [
            'id' => 4322402,
            'tx_ref' => '980072911353',
            'flw_ref' => 'FLW-MOCK-7f70b666dffd98457f15a7a257ec046a',
            'device_fingerprint' => 'N/A',
            'amount' => 10,
            'charged_amount' => 10,
            'app_fee' => 0.38,
            'merchant_fee' => 0,
            'processor_response' => 'Please enter the OTP sent to your mobile number 080****** and email te**@rave**.com',
            'auth_model' => 'VBVSECURECODE',
            'currency' => 'USD',
            'ip' => '52.209.154.143',
            'narration' => 'CARD Transaction ',
            'status' => 'pending',
            'payment_type' => 'card',
            'plan' => null,
            'fraud_status' => 'ok',
            'charge_type' => 'normal',
            'created_at' => '2023-05-11T12:13:09.000Z',
            'account_id' => 675999,
            'customer' => [
                'id' => 2066789,
                'phone_number' => null,
                'name' => 'Jack Alawa',
                'email' => 'majesty@test.com',
                'created_at' => '2023-05-11T12:13:09.000Z',
            ],
            'card' => [
                'first_6digits' => '418742',
                'last_4digits' => '4246',
                'issuer' => 'VISA ACCESS BANK PLC DEBIT CLASSIC',
                'country' => 'NG',
                'type' => 'VISA',
                'expiry' => '09/32',
            ],
        ],
        'meta' => [
            'authorization' => [
                'mode' => 'redirect',
                'redirect' => 'https://ravesandboxapi.flutterwave.com/mockvbvpage?ref=FLW-MOCK-7f70b666dffd98457f15a7a257ec046a&code=00&message=Approved. Successful&receiptno=RN1683807189334',
            ],
        ],
    ];
}

function makeItConsortiumPaymentResponse($override = [])
{
    return array_merge([
        'receiptNo' => '23050887F2995E',
        'sessionId' => '5b0a65f0b658e12990272c86aa5427aaced9f1db',
        'responseCode' => '01',
        'responseParams' => '',
        'responseMessage' => 'Success',
    ], $override);
}

function makeItConsortiumLookupResponse($override = [])
{
    return [
        'name' => 'JACK ALAWA',
        'charge' => 'NULL',
        'currency' => 'GHS',
        'accountNo' => null,
        'requestId' => null,
        'sessionId' => '5b0a65f0b658e12990272c86aa5427aaced9f1db',
        'accountRef' => 'PAS/20/01/2369',
        'extDetails' => '{"programme" :"Bachelor of Science in Physician Assistantship"}',
        'responseCode' => Arr::get($override, 'responseCode') ?? '01',
        'responseMessage' => 'Success',
    ];
}

function makeGmoneyCashinWebhookPayload(array $override = [])
{
    return '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
   <soapenv:Body>
      <api:Result xmlns:api="http://cps.huawei.com/cpsinterface/api_resultmgr" xmlns:com="http://cps.huawei.com/cpsinterface/common" xmlns:res="http://cps.huawei.com/cpsinterface/result">
         <res:Header>
            <res:Version>1.0</res:Version>
            <res:OriginatorConversationID>GW0000000000001795</res:OriginatorConversationID>
            <res:ConversationID>AG_20191203_00007bedc50c9a60aedf</res:ConversationID>
         </res:Header>
         <res:Body>
            <res:ResultType>0</res:ResultType>
            <res:ResultCode>0</res:ResultCode>
            <res:ResultDesc>Process service request successfully.</res:ResultDesc>
            <res:TransactionResult>
               <res:TransactionID>367779571058</res:TransactionID>
               <res:ResultParameters>
                  <res:ResultParameter>
                     <com:Key>DebitBalance</com:Key>
                     <com:Value>{"list":[{"accountno":"100000000110111034","accounttypename":"Customer E-Money Account","amount":"893.44","currency":"GHS"}],"total":[{"amount":"893.44","currency":"GHS"}]}</com:Value>
                  </res:ResultParameter>
                  <res:ResultParameter>
                     <com:Key>CreditBalance</com:Key>
                     <com:Value>{"list":[{"accountno":"100000000110151139","accounttypename":"Organization EMoneyAccount","amount":"829.32","currency":"GHS"}],"total":[{"amount":"829.32","currency":"GHS"}]}</com:Value>
                  </res:ResultParameter>
               </res:ResultParameters>
            </res:TransactionResult>
            <res:ReferenceData>
               <res:ReferenceItem>
                  <com:Key>Comment</com:Key>
                  <com:Value>Commission</com:Value>
               </res:ReferenceItem>
            </res:ReferenceData>
         </res:Body>
      </api:Result>
   </soapenv:Body>
</soapenv:Envelope>';
}

function makeWemaCashinWebhookPayload(array $override = []): array
{
    $webhookPayload = $override + [
        'originatoraccountnumber' => '7679632021',
        'amount' => '300000',
        'currency' => 'NGN',
        'originatorname' => 'DIGE GLOBAL RESOURCES',
        'narration' => '7679632021/73154420378/Ikenna Oliva',
        'craccountname' => 'Ikenna Oliva',
        'paymentreference' => '180485878',
        'reference' => 'unknown',
        'bankname' => 'FCMB',
        'sessionid' => '000003230207113138001912748869',
        'craccount' => '73154420378',
        'bankcode' => '000003',
        'created_at' => '0001-01-01T00:00:00',
    ];

    return $webhookPayload;
}

function makeBraasHashID($id)
{
    $salt = '!BRaaS-InboundPayoutChannels#';
    $hashId = new Hashids($salt, 10);
    $id = $hashId->encode($id);

    return $id;
}

function createNgMerchantUser($override = [])
{
    $client_type = ClientAccountType::factory()->merchant()->create();

    return User::factory()->for(Client::factory([
        'active_state' => 'active',
        'account_type' => $client_type->id,
    ]))->create($override + [
        'status' => 'active',
        'onboarding_stage' => 7,
    ]);
}

function encodePaymentMethod($id)
{
    $salt = '!BRaaS-InboundPayoutChannels#';
    $hashId = new Hashids($salt, 10);

    return $hashId->encode($id);
}

function mimickBrijXSendPaymentServiceInitiation()
{
    BrijXServiceTransaction::create([
        'service_id' => 'TRANSFER',
        'transaction_id' => 'SMP100',
        'brij_transaction_id' => 'BRXTRF2023022147937369816',
        'amount' => 100,
        'source_currency' => 'NGN',
        'destination_currency' => 'GHS',
        'description' => 'Sending something small',
        'status' => 'initiated',
        'rate' => 1.2,
        'request' => [
            'sender' => [
                'countryCode' => 'NG',
                'phone' => '+2341231233',
                'firstname' => 'Someone',
                'lastname' => 'FromNigeria',
            ],
            'beneficiary' => [
                'countryCode' => 'GH',
                'phone' => '+23354412312',
                'firstname' => 'Someone',
                'lastname' => 'InGhana',
            ],
        ],
    ]);
}

function createBrijxMerchantRole(string $permissionName = 'all')
{
    $brijxMerchant = Role::factory()->create([
        'name' => 'brijx-merchant',
        'display_name' => 'Brijx Merchant',
        'guard_name' => 'api',
        'description' => 'Users with this role are Brijx Merchant',
    ]);

    $permissions = [
        'brijx:cross-currency-transfer' => [
            'name' => 'brijx:cross-currency-transfer',
            'description' => 'Enables merchant to transfer from a local currency to another',
            'permission_category' => 'API Key Permissions',
            'guard_name' => 'api',
        ],
        'brijx:cross-currency-bill-payment' => [
            'name' => 'brijx:cross-currency-bill-payment',
            'description' => 'Enables merchant to pay a bill from a local currency to another',
            'permission_category' => 'API Key Permissions',
            'guard_name' => 'api',
        ],
        'brijx:cross-currency-wallet-fund' => [
            'name' => 'brijx:cross-currency-wallet-fund',
            'description' => 'Enables merchant to fund a brij users wallet from a local currency to another',
            'permission_category' => 'API Key Permissions',
            'guard_name' => 'api',
        ],
    ];

    if ($permissionName == 'all') {
        collect($permissions)->each(function ($record, $key) use ($brijxMerchant) {
            $permission = Permission::create($record);

            if ($permission->wasRecentlyCreated) {
                $brijxMerchant->givePermissionTo($permission);
            }
        });
    } else {
        $permission = $permission = Permission::create(Arr::get($permissions, $permissionName));

        if ($permission) {
            $brijxMerchant->givePermissionTo($permission);
        }
    }

    return $brijxMerchant;
}

// TODO: this function is doing too much
function createMerchant(
    int $onBoardingStage = 1,
    string $status = 'inactive',
    $overrides = [],
    $countryCurrency = 'GH_GHS'
): Client {
    $merchantTypeName = (Arr::get($overrides, 'merchant_type_name') != null) ? Arr::get($overrides, 'merchant_type_name') : 'General';
    $createAdmin = Arr::get($overrides, 'create_admin');

    $role = null;

    if (Arr::get($overrides, 'role')) {
        $role = Arr::get($overrides, 'role');
    } else {

        $role = Role::whereName('general-merchant')->first();

        if (! $role) {
            $role = Role::factory()->create([
                'name' => 'general-merchant',
                'display_name' => 'General Merchant',
                'guard_name' => 'api',
                'description' => 'Users with this role are Brij General merchants',
                'is_merchant' => 1,
            ]);
        }
    }

    MerchantType::firstOrCreate([
        'type_name' => $merchantTypeName,
        'description' => 'This describes the type of merchant',
    ]);

    $type = ClientAccountType::firstOrCreate([
        'name' => 'Merchant Account',
        'description' => 'This is a merchant account',
    ]);

    $industry = Arr::get($overrides, 'merchant_industry') ?? MerchantIndustry::firstOrCreate([
        'name' => 'Example Name',
        'label' => 'Example label',
        'code' => '000',
        'icon_url' => '/media/url/1.png',
        'color' => '#eee',
        'ranking' => 0,
    ]);

    $country = Arr::get($overrides, 'country');

    if (! $country) {
        $country = Country::whereCountryCurrency($countryCurrency)->first();
    }

    if (! $country) {
        $code = explode('_', $countryCurrency)[0];

        $country = Country::factory()->$code()->create();
    }

    $type = ClientAccountType::factory()->merchant()->create();

    $merchantId = Arr::get($overrides, 'merchant_id');

    $client = Client::firstOrCreate([
        'account_type' => $type->id,
        'business_name' => 'Test',
        'address' => 'test',
        'merchant_id' => $merchantId ?? '0000000005',
        'merchant_industry_code' => $industry->code,
        'active_state' => $status,
        'merchant_type_id' => $role->id,
    ]);

    $user = User::firstOrCreate([
        'phone' => '+233433523871',
        'email' => 'test@mail.com',
        'country_code' => $country->code,
        'is_merchant' => 'yes',
        'client_id' => $client->id,
        'onboarding_stage' => $onBoardingStage,
        'country_id' => $country->id,
        'firstname' => 'test',
        'lastname' => 'test',
        'password' => Hash::make('test'),
        'status' => $status,
    ]);

    $user->assignRole($role);

    if ($createAdmin) {
        Sanctum::actingAs(
            User::factory()->create(['is_admin' => 'yes']),
            ['*']
        );
    }

    return $client;
}

function makeKCBPayoutResponse($overrides = [])
{
    $kcbId = Arr::get($overrides, 'kcbId');
    $payeeName = Arr::get($overrides, 'payeeName');
    $brijTransactionId = Arr::get($overrides, 'brijTransactionId');
    $kcbStatus = Arr::get($overrides, 'kcbStatus');
    $ipnStatusDesc = Arr::get($overrides, 'ipnStatusDesc');
    $orderLineStatus = Arr::get($overrides, 'OrderLinesStatus');
    $orderLineStatusDesc = Arr::get($overrides, 'OrderLinesStatusDesc');
    $transactionType = Arr::get($overrides, 'transactionType');
    $bankCode = null;

    return [
        'Id' => $kcbId ?? 'Pi0_1387b54e-d0ad-cc90-7052-08db1f0aec5f',
        'Type' => $transactionType ?? 0,
        'Remarks' => 'Joan Shakila',
        'TypeDesc' => 'Account-To-Account',
        'CompanyId' => '00000000-0000-0000-0000-000000000000',
        'IPNEnabled' => false,
        'OrderLines' => [
            [
                'Type' => 0,
                'Payee' => $payeeName ?? 'Zab Zablet',
                'Amount' => 20,
                'MCCMNC' => 0,
                'Remark' => null,
                'Status' => $orderLineStatus ?? 6,
                'BankCode' => $bankCode ?? '02000',
                'TypeDesc' => null,
                'IPNStatus' => 0,
                'Reference' => $brijTransactionId ?? 'BRJINBREM2023030781277273',
                'MCCMNCDesc' => null,
                'ResultCode' => null,
                'ResultDesc' => null,
                'StatusDesc' => $orderLineStatusDesc ?? 'Submitted',
                'CreatedDate' => '2023-03-07T12:53:17.8868818',
                'IPNResponse' => null,
                'RemitterCCY' => 'KSH',
                'StoreNumber' => null,
                'PayeeAddress' => 'Brij Innovation Kenya',
                'RemitterName' => 'Joan Shakila',
                'ResponseCode' => null,
                'ResponseDesc' => null,
                'IPNStatusDesc' => $ipnStatusDesc ?? 'Pending',
                'PayeeIDNumber' => 'Kenya',
                'RecipientName' => 'Anaclate Lumumba',
                'TransactionID' => null,
                'PaymentPurpose' => 'Joan Shakila',
                'RemitterIDType' => '',
                'RecipientIDType' => null,
                'RemitterAddress' => null,
                'RemitterCountry' => 'Kenya',
                'RecipientAddress' => null,
                'RemitterIDNumber' => '',
                'RecipientIDNumber' => null,
                'TransactionNumber' => '204491639757',
                'TransactionReason' => null,
                'TransactionStatus' => null,
                'TransactionReceipt' => null,
                'RemitterDateOfBirth' => null,
                'RemitterIDIssueDate' => null,
                'RemitterNationality' => null,
                'RemitterPhoneNumber' => '0100407530900',
                'TransactionDateTime' => '2023-03-08 11:10:12',
                'PrimaryAccountNumber' => '0100407530900',
                'RecipientPhoneNumber' => null,
                'RemitterIDExpireDate' => null,
                'RemitterIDIssuePlace' => null,
                'RemitterSourceOfFunds' => 'Wallet',
                'DestinationCountryCode' => null,
                'SystemTraceAuditNumber' => $brijTransactionId ?? 'BRJINBREM2023030781277273',
                'TransactionCreditParty' => null,
                'RemitterPrincipalActivity' => null,
                'WalletAccountAvailableFunds' => 0,
                'RemitterFinancialInstitution' => 'Brij',
                'UtilityAccountAvailableFunds' => 0,
                'WorkingAccountAvailableFunds' => 0,
                'ChargePaidAccountAvailableFunds' => 0,
            ],
        ],
        'CallbackURL' => null,
        'CompanyDesc' => null,
        'IsDelivered' => true,
        'IPNDataFormat' => 0,
        'IPNDataFormatDesc' => 'XML',
    ];
}

function createFeeWallet($balance = 0, $currency = 'GHS')
{
    $countryCurrencyMap = [
        'GHS' => 'GH_GHS',
        'NGN' => 'NG_NGN',
        'KSH' => 'KE_KSH',
    ];

    $countryCurrency = Arr::get($countryCurrencyMap, $currency);

    $nameMap = [
        'GHS' => 'GH_GHS',
        'NGN' => 'NG_NGN',
        'KSH' => 'KE_KSH',
    ];

    $name = Arr::get($nameMap, $currency);

    WalletType::factory()->create([
        'name' => $name,
        'currency' => $currency,
        'country_currency' => $countryCurrency,
        'description' => 'Brij revenue wallet for Ghana',
        'is_internal' => 1,
        'category' => 'fees',
    ]);

    $financeAccountType = ClientAccountType::whereName('Finance Account')->first();

    if (! $financeAccountType) {
        $financeAccountType = ClientAccountType::factory()->create([
            'name' => 'Finance Account',
        ]);
    }

    $brijFinance = Client::with('user')->whereAccountType($financeAccountType->id)->first()?->user;

    if (! $brijFinance) {
        $brijFinance = createUser(clientOverride: [
            'account_type' => $financeAccountType->id,
        ]);
    }

    $map = [
        'GHS' => 'Cedi',
        'NGN' => 'Naira',
        'KSH' => 'Shilling',
    ];

    $wallettype = WalletType::whereCurrency($currency)->whereIsInternal(1)->first();

    if (! $wallettype) {
        $wallettype = WalletType::factory()->create([
            'currency' => $currency,
            'name' => $map[$currency],
            'is_internal' => 1,
            'category' => 'fees',
        ]);
    }

    return Wallet::factory()->create([
        'wallet_type_id' => $wallettype->id,
        'client_id' => $brijFinance->client_id,
        'balance' => $balance,
        'wema_ngn_account' => null,
    ]);
}

function createKshWallet($clientId, $balance = 0)
{
    $wallettype = WalletType::factory()->KEKSH()->create();

    return Wallet::factory()->create([
        'wallet_type_id' => $wallettype->id,
        'client_id' => $clientId,
        'balance' => $balance,
    ]);
}

function createWallet(
    int $clientId,
    float $balance = 0,
    string $countryCurrency = 'GH_GHS',
    ?string $name = null,
) {

    $legacyMap = [
        'GHS' => 'GH_GHS',
        'NGN' => 'NG_NGN',
        'KSH' => 'KE_KSH',
    ];

    $countryCurrency = $legacyMap[$countryCurrency] ?? $countryCurrency;

    $wallettype = WalletType::forCountryWallet($countryCurrency)->first();

    $modifier = str_replace('_', '', $countryCurrency);

    if (! $wallettype) {
        $wallettype = WalletType::factory()->$modifier()->create();
    }

    $country = createCountry($countryCurrency);

    CountryWalletType::create([
        'country_id' => $country->id,
        'wallet_type_id' => $wallettype->id,
    ]);

    $wallet = Wallet::factory()->create([
        'wallet_type_id' => $wallettype->id,
        'client_id' => $clientId,
        'balance' => 0,
        'wema_ngn_account' => ($countryCurrency == 'NG_NGN') ? 73154420378 : null,
        'name' => $name,
        'currency' => $wallettype->currency
    ]);

    if ($balance !== 0) {
        safelyCredit($wallet, $balance);
    }

    return $wallet->refresh();
}

function createCountry($countryCurrency = 'GH_GHS')
{
    $country = Country::whereCountryCurrency($countryCurrency)->first();

    if (! $country) {
        $code = explode('_', $countryCurrency)[0];
        $country = Country::factory()->$code()->create();
    }

    return $country;
}

function createBrijFeeWallet($currency = 'GHS', $balance = 0)
{
    $emailMap = [
        'GHS' => 'finance.gh@brij.money',
        'NGN' => 'finance.ng@brij.money',
        'KSH' => 'finance.ke@brij.money',
    ];

    $email = Arr::get($emailMap, $currency);

    $financeType = ClientAccountType::where('name', 'Finance Account')->first();

    $financeUser = User::whereEmail($email)->first();

    if(!$financeUser)
    {
       $financeUser = createUser(clientOverride:[
           'account_type' => $financeType->id,
       ]);
    }

    $wallettype = WalletType::factory()->fees($currency)->create([
        'currency' => $currency,
    ]);

    return Wallet::factory()->create([
        'wallet_type_id' => $wallettype->id,
        'client_id' => $financeUser->client_id,
    ]);
}

function createUser($override = [], $country = null, $clientOverride = []): ?User
{
    $client_type = ClientAccountType::factory()->personal()->create();

    $user = User::factory()->for(Client::factory([
        'merchant_id' => '000001',
        'active_state' => 'active',
        'account_type' => Arr::get($clientOverride, 'account_type') ?? $client_type->id,
        'business_name' => Arr::get($clientOverride, 'business_name') ?? 'JUMANJI Coders',
    ]))->create($override + [
        'status' => 'active',
        'onboarding_stage' => 4,
        'country_id' => $country?->id ?? 0,
        'firstname' => 'Zab',
        'lastname' => 'Zablet',
        'email' => 'zabzable@test.com',
    ]);

    $tokenCreator = new CreatePersonalAccessToken;

    $token = $tokenCreator->execute($user, 'master-token');

    savePlainTextToken($user, $token);

    return $user;
}

function createAdmin($override = [], $country = null, $clientOverride = []): ?User
{
    $client_type = ClientAccountType::factory()->personal()->create();

    $user = User::factory()->for(Client::factory([
        'active_state' => 'active',
        'account_type' => Arr::get($clientOverride, 'account_type') ?? $client_type->id,
        'business_name' => Arr::get($clientOverride, 'business_name') ?? 'JUMANJI Coders',
    ]))->create($override + [
        'status' => 'active',
        'onboarding_stage' => 4,
        'country_id' => $country?->id ?? 0,
        'firstname' => 'Zab',
        'lastname' => 'Zablet',
        'email' => 'zabzable@test.com',
        'is_admin' => 'yes',
    ]);

    $tokenCreator = new CreatePersonalAccessToken;

    $token = $tokenCreator->execute($user, 'master-token');

    savePlainTextToken($user, $token);

    $role = Role::whereName('admin')->first();

    if (blank($role)){
        $role = Role::factory()->create([
            'name' => 'admin',
            'display_name' => 'admin',
            'guard_name' => 'api',
            'description' => 'Users with this role are Brijx Merchant',
        ]);
    }

    $user->assignRole($role);

    return $user;
}

function savePlainTextToken($user, $token)
{
    $modelType = get_class($user);
    
    $access = PersonalAccessToken::where('tokenable_id', $user->id)
        ->where('name', 'master-token')->where('tokenable_type', $modelType)
        ->first();

    $access->plain_text_token = $token->plainTextToken;
    $access->expires_at = now()->addHours(TOKEN_EXPIRATION_IN_HOURS);

    return $access->save();
}

function createPaymentProvider($cashoutMethod, $providerName = '')
{
    $paymentProvider = ExternalPaymentProvider::factory()->create([
        'name' => $providerName,
    ]);

    $external = ExternalPaymentProviderCashOutMethod::factory()->create([
        'cash_out_method_id' => $cashoutMethod->id,
        'external_payment_provider_id' => $paymentProvider->id,
    ]);

    return $paymentProvider;
}

function createCashinPaymentProvider($cashinMethod, $name = 'Teller')
{
    $paymentProvider = ExternalPaymentProvider::factory()->create([
        'name' => $name,
    ]);

    $external = ExternalPaymentProviderCashInMethod::factory()->create([
        'cash_in_method_id' => $cashinMethod->id,
        'external_payment_provider_id' => $paymentProvider->id,
    ]);

    return $paymentProvider;
}

function makeCashinApiPath($wallet = null, $type = null, $network = null)
{
    return '/api/v2/clients/wallets/cashin';
}

function createApiMerchant($user = null, $roleName = null, $permissions = [])
{
    $role = Role::factory()->create([
        'name' => $roleName,
    ]);

    collect($permissions)->each(function ($record, $key) {
        $permission = Permission::create($record);
    });

    Permission::all()->each(function ($permission, $key) use ($role) {
        $role->givePermissionTo($permission);
    });

    $user->assignRole($role);
}

function mimickPayoutInitiation($user, $wallet = null, $sender = [], $beneficiary = [], $method = null)
{
    if (! $method) {
        $method = CashOutMethod::create([
            'name' => 'MTN Momo',
            'description' => 'Mobile Money',
            'channel' => 'mtnghana',
            'code' => '569',
            'fund_type' => 'momo',
            'priority' => 2,
            'supported_currencies' => [],
            'supported_country_currencies' => ['GH_GHS'],
            'icon_url' => '/media/icons/payment_methods/blank.png',
            'charge_code' => null,
            'supports_cashout' => false,
            'processor' => 'mfs',
        ]);
    }

    $walletTrx = WalletTransaction::create([
        'target_client_id' => $user->client->id,
        'source_client_id' => $user->client->id,
        'wallet_id' => $wallet?->id ?? 1,
        'transaction_id' => 'BRJ_OUB_REMKt9THwFqtJILYcAopF2CrZeT',
        'transaction_amount' => '000000010000',
        'amount_in_figures' => 100,
        'remark' => 'remittance',
        'currency' => 'NGN',
        'transaction_method' => $method->fund_type,
        'transaction_channel' => $method->channel,
        'channel_id' => $method->id,
        'status' => 'initiated',
        'status_reason' => 'Transaction Initiated',
        'app_fee' => 10,
        'wallet_balance' => 110,
        'balance_before' => 110,
        'mifos_transaction_sync_status' => 0,
        'momo_contact' => '+227123123444',
        'debit' => 0,
        'credit' => 0,
    ]);

    $braasTrx = BraasTransaction::create([
        'client_id' => $user->client->id,
        'wallet_tx_id' => $walletTrx->id,
        'transaction_id' => 'BRJ_OUB_REMKt9THwFqtJILYcAopF2CrZeT',
        'account_id' => null,
        'account_name' => null,
        'brij_transaction_id' => 'BRJ_OUB_REMKt9THwFqtJILYcAopF2CrZeT',
        'amount' => '100',
        'currency' => 'XOF',
        'bank_code' => null,
        'status' => 'initiated',
        'type' => 'outbound',
        'description' => 'Sending something small',
        'meta' => [
            'sender' => [
                'countryCode' => 'NG',
                'phone' => '+19858828066',
                'firstname' => 'Zab',
                'lastname' => 'Zablet',
            ],

            'beneficiary' => [
                'countryCode' => 'NE',
                'phone' => '+227123123444',
                'firstname' => 'Someone',
                'lastname' => 'InNiger',
            ],
            'rate' => [
                'from_currency' => 'NGN',
                'rate' => '0.78',
                'to_currency' => 'XOF',
            ],
        ],
        'source_currency' => 'NGN',
        'sender_amount' => '78',
        'retries' => 0,
        'processor_channel' => null,
        'request' => [
            'initiate_request' => [
                'transactionId' => 'ZENB2023108495',
                'amount' => '1.0',
                'currency' => 'NGN',
                'description' => 'Sending money to MTN Nigeria',
                'payoutMethodId' => 'kXLZkQ8jNG',
                'accountId' => '2347066467554',
            ],
        ],
    ]);
}

function makeGetTransStatusResponse(
    $statusCode = 'MR101',
    $statusMessage = 'success',
    $receivingPartnerId = '20841485774',
    $mfsTransId = '61670482176901',
    $brijTransId = '000000028882'
) {
    return "<?xml version='1.0' encoding='utf-8'?>
    <soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/'>
       <soapenv:Body>
           <ns:get_trans_statusResponse xmlns:ns='http://ws.mfsafrica.com'>
               <ns:return xmlns:ax21='http://mfs/xsd' xmlns:ax23='http://airtime/xsd' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:type='ax21:EResponse'>
                   <ax21:code xsi:type='ax21:Code'>
                       <ax21:status_code>$statusCode</ax21:status_code>
                   </ax21:code>
                   <ax21:e_trans_id>$receivingPartnerId</ax21:e_trans_id>
                   <ax21:message>$statusMessage</ax21:message>
                   <ax21:mfs_trans_id>$mfsTransId</ax21:mfs_trans_id>
                   <ax21:third_party_trans_id>$brijTransId</ax21:third_party_trans_id>
               </ns:return>
           </ns:get_trans_statusResponse>
       </soapenv:Body>
    </soapenv:Envelope>";
}

function makeGetRateResponse(
    $fromCurrency = null,
    $rate = '0.000000',
    $currency = 'GHS',
    $timestamp = '0000-00-00 00:00:00',
    $partnerCode = 'DUMMY'
) {
    return "<?xml version='1.0' encoding='UTF-8'?>
    <soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/'>
       <soapenv:Body>
          <ns:get_rateResponse xmlns:ns='http://ws.mfsafrica.com' xmlns:ax21='http://mfs/xsd' xmlns:ax23='http://airtime/xsd'>
             <ns:return xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:type='ax21:Rate'>
                <ax21:from_currency>$fromCurrency</ax21:from_currency>
                <ax21:fx_rate>$rate</ax21:fx_rate>
                <ax21:partner_code>$partnerCode</ax21:partner_code>
                <ax21:time_stamp>$timestamp</ax21:time_stamp>
                <ax21:to_currency>$currency</ax21:to_currency>
             </ns:return>
          </ns:get_rateResponse>
       </soapenv:Body>
    </soapenv:Envelope>";
}

function makeAccountValidationResponse(
    $msidn = '',
    $status = 'Active',
    $partnerCode = '',
) {
    return "<?xml version='1.0' encoding='UTF-8'?>
    <soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/'>
       <soapenv:Body>
          <ns:account_requestResponse xmlns:ns='http://ws.mfsafrica.com'>
             <ns:return xmlns:ax21='http://mfs/xsd' xmlns:ax23='http://airtime/xsd' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:type='ax21:Wallet'>
                <ax21:msisdn>$msidn</ax21:msisdn>
                <ax21:partner_code>$partnerCode</ax21:partner_code>
                <ax21:status xsi:type='ax21:Status'>
                   <ax21:status_code>$status</ax21:status_code>
                </ax21:status>
             </ns:return>
          </ns:account_requestResponse>
       </soapenv:Body>
    </soapenv:Envelope>";
}

function makeCommitResponse(
    $statusCode = 'MR101',
    $statusMessage = 'success',
    $receivingPartnerId = '20841485774',
    $mfsTransId = '61670482176901',
    $brijTransId = '000000028882'
) {
    return "<?xml version='1.0' encoding='UTF-8'?>
    <soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/'>
       <soapenv:Body>
          <ns:trans_comResponse xmlns:ns='http://ws.mfsafrica.com'>
             <ns:return xmlns:ax21='http://mfs/xsd' xmlns:ax23='http://airtime/xsd' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:type='ax21:EResponse'>
                <ax21:code xsi:type='ax21:Code'>
                   <ax21:status_code>$statusCode</ax21:status_code>
                </ax21:code>
                <ax21:e_trans_id>$receivingPartnerId</ax21:e_trans_id>
                <ax21:message>$statusMessage</ax21:message>
                <ax21:mfs_trans_id>$mfsTransId</ax21:mfs_trans_id>
                <ax21:third_party_trans_id>$brijTransId</ax21:third_party_trans_id>
             </ns:return>
          </ns:trans_comResponse>
       </soapenv:Body>
    </soapenv:Envelope>";
}

function makeRemittLogResponse(
    $rate = null,
    $mfsTransId = null,
    $partnerCode = null,
    $receiveAmt = null,
    $currencyCode = null,
    $brijTransId = null,
    $statusCode = null,
    $message = null,
    $sendAmount = null
) {
    return "<?xml version='1.0' encoding='UTF-8'?>
        <soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/'>
        <soapenv:Body>
            <ns:mm_remit_logResponse xmlns:ns='http://ws.mfsafrica.com'>
            <ns:return xmlns:ax21='http://mfs/xsd' xmlns:ax23='http://airtime/xsd' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:type='ax21:TransactionWallet'>
                <ax21:fx_rate>$rate</ax21:fx_rate>
                <ax21:mfs_trans_id>$mfsTransId</ax21:mfs_trans_id>
                <ax21:name_match xsi:nil='true' />
                <ax21:partner_code>$partnerCode</ax21:partner_code>
                <ax21:receive_amount xsi:type='ax21:Money'>
                    <ax21:amount>$receiveAmt</ax21:amount>
                    <ax21:currency_code>$currencyCode</ax21:currency_code>
                </ax21:receive_amount>
                <ax21:sanction_list_recipient xsi:nil='true' />
                <ax21:sanction_list_sender xsi:nil='true' />
                <ax21:send_amount xsi:type='ax21:Money'>
                    <ax21:amount>$sendAmount</ax21:amount>
                    <ax21:currency_code>USD</ax21:currency_code>
                </ax21:send_amount>
                <ax21:status xsi:type='ax21:MResponse'>
                <ax21:code xsi:type='ax21:Code'>
                    <ax21:status_code>$statusCode</ax21:status_code>
                </ax21:code>
                <ax21:message>$message</ax21:message>
                </ax21:status>
                <ax21:third_party_trans_id>$brijTransId</ax21:third_party_trans_id>
            </ns:return>
            </ns:mm_remit_logResponse>
        </soapenv:Body>
    </soapenv:Envelope>";
}
