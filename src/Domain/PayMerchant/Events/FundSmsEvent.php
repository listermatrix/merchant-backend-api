<?php

namespace Domain\PayMerchant\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FundSmsEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $contact;

    public $message;
    
    public $walletTransaction;

    public $user;

    public function __construct($contact, $message, $walletTransaction=null, $user=null)
    {
        $this->contact = $contact;

        $this->message = $message;

        $this->walletTransaction = $walletTransaction;

        $this->user = $user;
    }
}
