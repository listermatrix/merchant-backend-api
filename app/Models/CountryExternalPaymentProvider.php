<?php

namespace App\Models;

use Dyrynda\Database\Casts\EfficientUuid;
use Dyrynda\Database\Support\GeneratesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountryExternalPaymentProvider extends Model
{
    use GeneratesUuid,HasFactory;

    protected $fillable = ['uuid', 'country_id', 'external_payment_provider_id'];

    protected $casts = [
        'uuid' => EfficientUuid::class,
    ];
}
