<?php

namespace App\Models;

use Spatie\Image\Manipulations;
use Spatie\MediaLibrary\HasMedia;
use App\Helpers\Media\BrijMediaUtil;
use Illuminate\Database\Eloquent\Model;
use Dyrynda\Database\Casts\EfficientUuid;
use Dyrynda\Database\Support\GeneratesUuid;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\BrijxIntentFulfillmentRequest;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Client extends Model implements HasMedia
{
    use BrijMediaUtil, GeneratesUuid, HasFactory, InteractsWithMedia;

    protected $fillable = [
        'uuid',
        'account_type',
        'client_id',
        'mothers_maiden_name',
        'certificate_of_registration_url',
        'certificate_of_commencement_url',
        'verification_id_url',
        'selfie_url',
        'no_of_employees',
        'business_name',
        'merchant_id',
        'merchant_category_code',
        'address',
        'business_logo_url',
        'business_type',
        'business_address',
        'business_description',
        'business_email',
        'business_twitter_profile',
        'business_facebook_profile',
        'business_instagram_profile',
        'payment_link',
        'active_state',
        'business_phone',
        'business_website',
        'business_region',
        'business_city',
        'business_staff_size',
        'business_industry_code',
        'merchant_industry_code',
        'business_description',
        'business_type_code',
        'compliance_approved_at',
        'merchant_business_type_id',
        'merchant_type_id',
        'registered_by',
        'business_linkedin_profile',
        'tax_identification_number',
        'over_all_kyb_status',
        'redirect_after_payment',
        'payment_redirect_url',
        'pretty_payment_link',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    public function merchantIndustry(): BelongsTo
    {
        return $this->belongsTo(MerchantIndustry::class, 'merchant_industry_code', 'code');
    }

    public function merchantcategory()
    {
        return $this->belongsTo(MerchantCategory::class, 'merchant_category_code', 'code');
    }

    public function merchantservices(): HasMany
    {
        return $this->hasMany(MerchantService::class, 'client_id');
    }

    public function merchantproducts()
    {
        return $this->hasMany(MerchantProduct::class, 'client_id');
    }

    public function clientaccounttype()
    {
        return $this->belongsTo(ClientAccountType::class, 'account_type');
    }

    public function targetclientwallettransactions()
    {
        return $this->hasMany(WalletTransaction::class, 'target_client_id');
    }

    public function sourceclientwallettransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'source_client_id');
    }

    public function paymentrequests(): HasMany
    {
        return $this->hasMany(RequestPayment::class, 'paying_client_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'client_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'client_id');
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'client_id');
    }

    public function merchantsearch()
    {
        return $this->hasOne(MerchantSearch::class, 'client_id');
    }

    public function merchantMiniApp()
    {
        return $this->hasMany(MerchantMiniApp::class);
    }

    public function chakaUser()
    {
        return $this->hasMany(ChakaUser::class);
    }

    public function configuration()
    {
        return $this->hasOne(MerchantConfiguration::class);
    }

    protected $casts = [
        'uuid' => EfficientUuid::class,
    ];

    public function merchant_type()
    {
        return $this->belongsTo(MerchantType::class);
    }

    public function flutterwavePayoutSubAccount()
    {
        return $this->hasOne(FlutterwavePayoutSubAccount::class, 'client_id');
    }

    public function merchant_business_documents()
    {
        return $this->hasMany(MerchantBusinessDocument::class, 'client_id');
    }

    public function selfie()
    {
        return $this->belongsTo(Media::class, 'selfie_media_id');
    }

    public function balanceAlertSubscription()
    {
        return $this->hasOne(BalanceAlertSubscription::class, 'client_id');
    }

    public function paymentCampaigns()
    {
        return $this->hasMany(PaymentCampaign::class, 'client_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection($this->mediaCollection())
            ->useDisk($this->mediaDisk());

        $this->addMediaCollection($this->selfieCollection())
            ->useDisk($this->mediaDisk());
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion($this->selfieThumbName())
            ->performOnCollections($this->selfieCollection())
            ->fit(Manipulations::FIT_CROP, 128, 128);
    }

    public function getSelfieUrlAttribute($value): ?string
    {
        return $this->getSelfieUrl($this);
    }

    public function invoice(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function walletType(): BelongsToMany
    {
        return $this->belongsToMany(WalletType::class, 'wallets', 'client_id');
    }

    public function discount(): HasMany
    {
        return $this->hasMany(MerchantDiscount::class);
    }

    public function tax(): HasMany
    {
        return $this->hasMany(MerchantTax::class);
    }

    public function invoiceServices(): HasMany
    {
        return $this->hasMany(MerchantInvoiceService::class);
    }

    public function paymentLinkTemplates(): HasMany
    {
        return $this->hasMany(PaymentLinkTemplate::class);
    }


    public function brijXIntent(): HasMany
    {
        return $this->hasMany(BrijxIntentFulfillmentRequest::class, 'fulfillment_partner_id');
    }

    public function merchantPaymentChannelSettings()
    {
        return $this->hasMany(MerchantPaymentChannelSetting::class, 'client_id');
    }

    public function pinkey()
    {
        return $this->hasOne(UserPinKey::class, 'client_id');
    }

    public function accountModification(): HasMany
    {
        return $this->hasMany(AccountModificationRequest::class);
    }

    public function merchantBusinessType(): BelongsTo
    {
        return $this->belongsTo(MerchantBusinessType::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function client2fa()
    {
        return $this->hasOne(Client2FA::class);
    }

    public function remittanceDestinationRails()
    {
        return $this->belongsToMany(RemittanceDestinationRail::class, 'client_remittance_destinations')
            ->with(['country', 'cashoutMethod'])
            ->withTimestamps();
    }
}
