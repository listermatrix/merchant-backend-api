<?php
namespace Domain\ApiClients;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;


class SaanaPay
{

    private const STATUS_PENDING = 'pending';
    private const STATUS_FAILED = 'failed';
    private const HTTP_SUCCESS = 200;
    private const HTTP_ERROR = 422;


    protected $token;
    protected $baseURL;
    protected $expiresAt;
    protected $prefixAccountName;
    protected $cashoutBaseUrl;
    protected $cashoutAPIKey;

    public function __construct()
    {
        $this->token = config('services.saanapay.collection.api_token');
        $this->baseURL = config('services.saanapay.collection.base_url');
        $this->expiresAt = config('services.saanapay.collection.request_expires');
        $this->prefixAccountName = config('services.saanapay.collection.account_name_prefix');

        $this->cashoutBaseUrl = config('services.saanapay.payout.base_url');
        $this->cashoutAPIKey = config('services.saanapay.payout.api_token');
    }

    /**
     * Create a payment request.
     * @param array $payload - The payload for the payment request.
     * @return array - The response from the API.
    */
    public function createPaymentRequest(array $payload): array
    {
        $payload = [
            'name' =>  Arr::get($payload, 'customerName'),
            'email' =>  Arr::get($payload, 'customerEmail'),
            'amount' =>  Arr::get($payload, 'amount'),
            'request_id' => Arr::get($payload, 'transactionId'),
        ];

        $url = $this->baseURL . '/payments/create';

        $response = Http::withToken($this->token)->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($url, $payload)->json();
        
        return $response;
    }

    /**
     * Retrieve the charge amount for a transaction.
     * @param string $transactionId - brij transaction id
     * @return array - The response from the API.
    */
    public function getChargeAmount(string $transactionId): array
    {
        $url = $this->baseURL. "/payments/get_charge?request_id=$transactionId&channel=bank_transfer";

        $response = Http::withToken($this->token)->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->get( $url )->json();

        return $response;
    }

    /**
     * Initiate a direct bank transfer.
     * @param string $transactionId - brij transaction id
     * @param float $amount - The amount to transfer.
     * @return array - The response from the API.
     * 
    */
    public function makeDirectBankTransfer(string $transactionId, float $amount): array
    {
        
        $payload = [
            'expires' =>  $this->expiresAt,
            'request_id' =>  $transactionId,
            'account_name_prefix' => $this->prefixAccountName,
            'amount' =>  $amount,
        ];
        
        $url = $this->baseURL . '/payments/process/bank_transfer';
        $response = Http::withToken($this->token)->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
        ->post($url, $payload)->json();

        return $response;
    }

    /**
     * Requery a transaction
     * @param string $transactionId - brij transaction id
     * @return array - The response from the API.
     * 
    */
    public function requeryTransaction(string $transactionId): array
    {
        $url = $this->baseURL. "/payments/validate?request_id=$transactionId";

        $response = Http::withToken($this->token)->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->get( $url )->json();

        return $response;
    }

    public function debitCard(array $payload): array
    {
        $url = $this->baseURL . '/payments/process/card';
        $response = Http::withToken($this->token)->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
        ->post($url, $payload)->json();

        return $response;
    }


    /**
     *  CASHOUT TRANSACTIONS BELOW
     * 
    */

    /**
     * Requery cashout transaction status from SaanaPay
     *
     * @param string [brij] $transactionId
     * @return array|null API response or null on failure
     */
    public function requeryCashoutTransaction(string $transactionId): ?array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->cashoutBaseUrl}/requery", [
                'request_id' => $transactionId
            ]);

        $responseData = $response->json();

        logger()->channel('saanapay')->info(
            "NGN Payout Requery Response for transaction {$transactionId}",
            [$responseData]
        );

        return $responseData;
    }

    /**
     * Resolve bank account details
     *
     * @param array $payload Account resolution payload
     * @return array|null API response or null on failure
     */
    public function resolveAccount(array $payload): ?array
    {   
        return Http::withHeaders($this->getHeaders())
            ->post("{$this->cashoutBaseUrl}/resolveaccount", $payload)
            ->json();
    }

    /**
     * Get common HTTP headers for API requests
     *
     * @return array
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->cashoutAPIKey}",
            'Accept' => 'application/json',
        ];
    }

    private function buildFailedResponse(): array
    {
        return [
            'status' => self::STATUS_FAILED,
            'statusCode' => self::HTTP_ERROR,
            'httpCode' => self::HTTP_ERROR,
            'description' => null,
            'errors' => null,
        ];
    }

    private function formatCurrencyInMessage(string $message): string
    {
        if (preg_match('/\bamount\s+\d+/i', $message)) {
            return preg_replace('/(\bamount\s+)(\d+(?:\.\d{2})?)/i', '$1' . 'NGN' . ' $2', $message);
        }

        return $message;
    }


    /**
     * Build standardized response from API response
     *
     * @param string $responseBody Raw response body
     * @return array Standardized response format
     */
    public function buildResponse(string $responseBody): array
    {
        try {
            
            logger()->channel('saanapay')->info('NGN Cashout Response', [$responseBody]);

            $body = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            $isSuccess = Arr::get($body, 'status', false);

            $status = $isSuccess ? self::STATUS_PENDING : self::STATUS_FAILED;
            $httpCode = $isSuccess ? self::HTTP_SUCCESS : self::HTTP_ERROR;

            return [
                'status' => $status,
                'statusCode' => $httpCode,
                'httpCode' => $httpCode,
                'description' => $this->formatCurrencyInMessage(Arr::get($body, 'data.message')),
                'errors' => Arr::get($body, 'data.errors'),
            ];

        } catch (Exception $e) {
            logger()->channel('saanapay')->error('Failed to parse SaanaPay response', [
                'response' => $responseBody,
                'error' => $e->getMessage()
            ]);

            return $this->buildFailedResponse();
        }
    }

    /**
     * Initiate payout request to SaanaPay API
     *
     * @param object $dto Data transfer object containing payload
     * @return array [processed_response, raw_response]
     */
    public function initiatePayout(array $data): array
    {
        $rawResponse = Http::withHeaders($this->getHeaders())
            ->post("{$this->cashoutBaseUrl}/sendmoney", $data);

        $processedResponse = $this->buildResponse($rawResponse->body());

        return [$processedResponse, $rawResponse];
    }

    public function retrieveAccountBalance(): array
    {
       return Http::withHeaders($this->getHeaders())
            ->get("{$this->cashoutBaseUrl}/balance")
            ->json();

    }

}