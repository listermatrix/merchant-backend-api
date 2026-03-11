<?php

namespace App\Http\Resources;

use App\Http\Resources\Brijx\BrijxOfferResource;
use App\Models\MtnGhanaBrijXTransaction;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class WalletTransactionResource extends JsonResource
{
    public function toArray($request)
    {
        return array_merge($this->baseTransactionData(), []);
    }

    private function baseTransactionData()
    {
        $author = null;

        if( !blank( $this->author_id ) && !blank( $this->author_type ) ) {
             $author = getAuthor($this->author_type, $this->author_id);
        }

        return [
            'id' => $this->uuid,
            'app_fee' => $this->app_fee,
            'card_no' => $this->card_number,
            'transaction_id' => $this->transaction_id,
            'checkout_id' => $this->getCheckoutId(),
            'amount' => $this->transaction_amount,
            'amount_in_figures' => $this->amount_in_figures,
            'currency' => $this->currency,
            'transaction_type' => $this->remark,
            'transaction_method' => $this->transaction_method,
            'transaction_channel' => $this->transaction_channel,
            'momo_contact' => $this->momo_contact ?? $this->getReceipientAccountId(),
            'bank_account' => $this->bank_account,
            'status' => $this->status,
            'brijx_id' => $this->brijx_id,
            'status_reason' => $this->status_reason,
            'balance' => $this->wallet_balance,
            'narration' => $this->remark,
            'position' => $this->position,
            'balance_before' => $this->balance_before,
            'source_client' => new ClientForWalletTransactionResource($this->sourceclient),
            'target_client' => new ClientForWalletTransactionResource($this->targetclient),
            'wallet_icon_url' => $this->wallet->iconUrl(), //TODO: write a faster query
            'meta' => $this->meta,
            'date' => $this->created_at,
            'brijxoffer' => new BrijxOfferResource($this->brijxoffer),
            'beneficiary_details' => $this->getBeneficiaryDetails(),
            'ticket_id' => $this->ticket?->uuid,
            'ticket_status' => $this->ticket?->status,
            'payment_provider_status_message' => $this->payment_provider_status_message,
            'author' => [
                'id'=> Arr::get($author, 'id'),
                'firstname' => Arr::get($author, 'firstname'),
                'lastname' => Arr::get($author, 'lastname'),
                'phone' => Arr::get($author, 'phone'),
                'email'=> Arr::get($author, 'email'),
            ]
        ];
    }

    private function getCheckoutId()
    {
        $id = null;
        $slashMerchIdCount = 7;

        if (isset($this->meta['checkout_transaction_id'])) {
            $id = substr($this->meta['checkout_transaction_id'], $slashMerchIdCount);
        }

        return $id;
    }

    private function getBeneficiaryDetails()
    {
        if (Arr::get($this->meta, 'beneficiary_details')) {
            return [
                'beneficiary_name' => Arr::get($this->meta, 'beneficiary_details.beneficiary_name'),
                'account_id' => Arr::get($this->meta, 'beneficiary_details.account_id'),
            ];
        } else {
            return [
                'beneficiary_name' => $this->getClientName(),
                'account_id' => $this->momo_contact ?? $this->bank_account
            ];
        }
    }

    private function getClientName()
    {
        $user = optional(optional($this->targetclient)->user);

        return $user->is_merchant == 'yes'
            ? optional($this->targetclient)->business_name
            : $user->fullname;
    }

    private function getReceipientAccountId()
    {
        $externalApps = env('ALLOWED_EXTERNAL_APPS_MERCHANTS');
        $user = auth()->user();
        if (in_array($user->client->merchant_id, explode('|', $externalApps))){
            return MtnGhanaBrijXTransaction::where('brij_transaction_id', $this->transaction_id)->first()?->beneficiary_account_id;
        }

        return null;
    }
}
