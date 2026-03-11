<?php

namespace App\Models;

use Dyrynda\Database\Casts\EfficientUuid;
use Dyrynda\Database\Support\GeneratesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletAbility extends Model
{
    use GeneratesUuid, HasFactory;

    protected $casts = [
        'uuid' => EfficientUuid::class,
    ];
}
