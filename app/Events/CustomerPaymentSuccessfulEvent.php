<?php

namespace App\Events;

use App\Models\WalletTransaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CustomerPaymentSuccessfulEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(public WalletTransaction $walletTrxn, public string $customerEmail, public ?string $customerName=null, public ?string $description=null, public ?string $message=null, public ?string $filename = null)
    {
        //
    }

}
