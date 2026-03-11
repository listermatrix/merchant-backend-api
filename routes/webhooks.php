<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/**
 * ---------------------------------------------------------
 *  ACTIVE ROUTES GOES BELOW
 * --------------------------------------------------------
*/


Route::webhooks('/paystack', 'paystack')->middleware('throttle:paystack-webhook-notification');
Route::webhooks('/saanapay', 'saanapay')->middleware('throttle:saanapay-webhook-notification');


