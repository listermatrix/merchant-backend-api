<?php

namespace App\Models;

use App\Models\CustomerPaymentDetail;
use Illuminate\Database\Eloquent\Model;
use Dyrynda\Database\Casts\EfficientUuid;
use Dyrynda\Database\Support\GeneratesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use GeneratesUuid,HasFactory;

    protected $fillable = ['firstname', 'lastname', 'email', 'phone', 'country_id', 'type', 'is_favorite'];

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function merchants()
    {
        return $this->belongsToMany(Merchant::class);
    }

    public function getFullNameAttribute()
    {
        return $this->firstname.' '.$this->lastname;
    }

    protected $casts = [
        'uuid' => EfficientUuid::class,
    ];

    public function customerPaymentDetail()
    {
        return $this->hasOne(CustomerPaymentDetail::class);
    }
}
