<?php

namespace App\Models;

use App\Models\InvoicePaymentLog;
use App\Models\TemboplusMomoTrxn;
use Illuminate\Database\Eloquent\Model;
use Dyrynda\Database\Casts\EfficientUuid;
use App\Models\ManualWalletTrxnResolution;
use Dyrynda\Database\Support\GeneratesUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Domain\Fund\QueryBuilders\WalletTransactionQueryBuilder;

class WalletTransaction extends Model
{
    use GeneratesUuid, HasFactory;

    public const SUCCESSFUL = 'successful';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public const INITIATED = 'initiated';

    public const TIMEOUT = 'timeout';

    public const OPEN_URL = 'open_url';

    protected $fillable = [
        'wallet_balance',
        'brijx_id',
        'momo_contact',
        'transaction_id',
        'transaction_amount',
        'amount_in_figures',
        'remark',
        'source_client_id',
        'wallet_id',
        'status',
        'status_reason',
        'card_number',
        'transaction_method',
        'transaction_channel',
        'target_client_id',
        'currency',
        'hash',
        'balance_before',
        'expires_at',
        'meta',
        'internal_escrow_id',
        'mifos_transaction_sync_status',
        'app_fee',
        'momo_contact',
        'created_at',
        'updated_at',
        'position',
        'bank_account',
        'settled_at',
        'revenue_transaction_id',
        'channel_id',
        'processor',
        'processor_transaction_id',
        'author_type',
        'author_id',
        'fee_type',
        'fee_value',
        'brij_marked_up_rate',
        'service_provider_rate',
        'fee_code_applied',
        'wallet_transaction_id',
        'channel_type',
        'description',
        'payment_provider_status_message'
    ];

    public function newEloquentBuilder($query): WalletTransactionQueryBuilder
    {
        return new WalletTransactionQueryBuilder($query);
    }

    public function braastrx()
    {
        return $this->hasOne(BraasTransaction::class, 'wallet_tx_id');
    }

    public function paymentcheckout()
    {
        return $this->hasOne(PaymentCheckout::class, 'internal_trx_id', 'transaction_id');
    }

    public function wematemporaryaccount()
    {
        return $this->hasOne(WemaTemporaryAccount::class, 'transaction_id');
    }

    public function paymentreceipt()
    {
        return $this->hasOne(PaymentReceipt::class, 'wallet_transaction_id');
    }

    public function targetclient()
    {
        return $this->belongsTo(Client::class, 'target_client_id');
    }

    public function internalescrow()
    {
        return $this->belongsTo(InternalEscrow::class, 'internal_escrow_id');
    }

    public function sourceclient()
    {
        return $this->belongsTo(Client::class, 'source_client_id');
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function brijxoffer()
    {
        return $this->belongsTo(BrijXOffer::class, 'brijx_id');
    }

    public function brijXMtnTransaction()
    {
        return $this->hasOne(MtnGhanaBrijXTransaction::class, 'wallet_trx_id', 'id');
    }

    protected $casts = [
        'uuid' => EfficientUuid::class,
        'meta' => 'array',
    ];

    protected $dates = [
        'settled_at',
    ];

    public function external_biller_transaction()
    {
        return $this->hasMany(ExternalBillerTransactions::class);
    }

    public function invoicePaymentLog()
    {
        return $this->hasOne(InvoicePaymentLog::class, 'wallet_transaction_id', 'id');
    }

    public function temboplusMomoTrxn()
    {
        return $this->hasOne(TemboplusMomoTrxn::class, 'wallet_transaction_id', 'id');
    }

    public function temboPlusRequestId(): HasMany
    {
        return $this->hasMany(TemboPlusTransactionRequestID::class);
    }

    public function revenueSplit()
    {
        return $this->hasOne(BrijRevenueTransactions::class, 'transaction_id');
    }

    public function directBankTransferTemporaryAccount()
    {
        return $this->hasOne(DirectBankTransferTemporaryAccount::class, 'wallet_transaction_id');
    }

    public function manualWalletTrxnResolution()
    {
        return $this->hasOne(ManualWalletTrxnResolution::class, 'wallet_transaction_id');
    }
    public function ticket()
    {
        return $this->hasOne(Ticket::class);
    }
}
