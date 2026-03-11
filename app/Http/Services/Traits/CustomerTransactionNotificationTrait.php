<?php
namespace App\Http\Services\Traits;

use App\Jobs\ConstructInvoiceReceiptJob;
use Illuminate\Support\Arr;
use App\Models\WalletTransaction;
use App\Events\CustomerPaymentSuccessfulEvent;

trait CustomerTransactionNotificationTrait
{
   public function customerTrxnNotifyReceipt(string $walletTrxnUuid)
   {

     $walletTrxn = WalletTransaction::whereUuid($walletTrxnUuid)->with(['wallet.client.user'])->first();

     $customer = Arr::get($walletTrxn, 'meta.customer');

     if( blank( $customer ) ) {
        return;
     }

     $recipient = $this->getRecipientInfo($walletTrxn);

     $sms = $this->constructCustomerSMS($walletTrxn, $recipient);

     if ($walletTrxn->remark === 'invoice') {
         ConstructInvoiceReceiptJob::dispatch($walletTrxn, $customer, $sms);
     } else {
         CustomerPaymentSuccessfulEvent::dispatch($walletTrxn, Arr::get($customer, 'email'), Arr::get($customer, 'name'), Arr::get($customer, 'description'), $sms );
     }
   }

   private function constructCustomerSMS($walletTrxn, $recipientName): string
   {
      $message = "Payment Successful\n\n";

      $message .= "Merchant/ExportRecipientMail: $recipientName\n";

      $message .= "Trxn ID: {$walletTrxn->transaction_id}\n";

      $message .= "Trxn Type: " . getWalletTransactionRemark($walletTrxn->remark) . "\n";

      if($walletTrxn->fee_bearer === 'customer') {
        $message .= "Amt: {$walletTrxn->currency}" . formatAmountWithCommas($walletTrxn->amount_in_figures) . "\n";
        $message .= "Trxn Fee: {$walletTrxn->currency}" . formatAmountWithCommas($walletTrxn->app_fee) . "\n";
      } else {

        // when merchant bears transaction fee, notify customer of full amount
        $message .= "Amt: {$walletTrxn->currency}" . formatAmountWithCommas( ($walletTrxn->amount_in_figures + $walletTrxn->app_fee) ) . "\n";

      }

      $message .="DT: {$walletTrxn->created_at->format('Y-m-d h:i:s a')}\n";

      return $message;

   }

   private function getRecipientInfo($walletTrxn)
   {
       $recipient = '';
       $user = $walletTrxn->wallet->client->user;

       if ($user->is_merchant == 'yes' && !blank($walletTrxn->wallet->client->business_name)) {
           $recipient = $walletTrxn->wallet->client->business_name;
       } else {
           $firstName = $user->firstname ?? '';
           $lastName = $user->lastname ?? '';
           $recipient = trim("{$firstName} {$lastName}");
       }

       return mb_strimwidth(( $recipient ), 0, 20, '...');
   }
}
