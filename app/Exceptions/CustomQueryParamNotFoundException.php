<?php

namespace App\Exceptions;

use Exception;

class CustomQueryParamNotFoundException extends Exception
{
    public function __construct($message)
    {
        $this->message = $message;
    }

    public function render($message)
    {
        return response()->json([
            'status' => QUERY_PARAM_NOT_FOUND_IN_URL_CODE,
            'errors' => [
                'query_param' => $this->message,
            ],
            'message' => 'An expected query param was not found in url.',
        ], 400);
    }
}
