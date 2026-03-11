<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {

            // app(ExceptionTracker::class)->report($e);

            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($request->is([
            'api/v2/brijxthirdparty/mtn-brijxbank/getfinancialresourceinformation',
            'api/v2/brijxthirdparty/mtn-brijxbank/payment',
            'api/v2/braas/mtn-outbound',
        ])) {
            $this->logErrorToFile($exception);
        }
        if ($exception instanceof \App\Exceptions\CustomBadRequestException) {
            if ($request->is(['api/v2/brijxthirdparty/mtn-brijxbank/getfinancialresourceinformation'])) {
                $message = $exception->getMessage();

                $xmlContent = $this->mtnXmlGetFriFailedResponse($message);

                return response($xmlContent, 400, ['Content-Type' => 'application/xml']);
            }
            if ($request->is(['api/v2/brijxthirdparty/mtn-brijxbank/payment'])) {
                $message = $exception->getMessage();

                $transactionId = $this->getTranactionId();

                $xmlContent = $this->mtnXmlFailedPaymentRequestResponse($message, $transactionId);

                return response($xmlContent, 400, ['Content-Type' => 'application/xml']);
            }
            if ($request->is(['api/v2/braas/mtn-outbound'])) {
                $message = $exception->getMessage();

                $transactionId = $this->getTranactionId();

                if (isset($transactionId)) {
                    $xmlContent = $this->mtnXmlFailedPaymentRequestResponse($message, $transactionId);

                    return response($xmlContent, 400, ['Content-Type' => 'application/xml']);
                }

                $xmlContent = $this->mtnXmlGetFriFailedResponse($message);

                return response($xmlContent, 400, ['Content-Type' => 'application/xml']);
            }
        }

        return parent::render($request, $exception);
    }



    private function getTranactionId()
    {
        $requestContent = request()->getContent();

        $data = json_decode($requestContent, true);

        $xmlContent = $data['xml_data'];

        $xmlObject = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);

        $data = json_decode(json_encode($xmlObject), true);

        $transactionId = Arr::get($data, 'transactionid');

        return $transactionId;
    }

    private function logErrorToFile($exception)
    {
        $requestContent = request()->getContent();

        $data = json_decode($requestContent, true);

        $xmlContent = $data['xml_data'];

        Log::channel('outbounderrors')->info($exception->getMessage(), [
            'request' => $xmlContent,
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
