<?php

namespace Domain\PayMerchant\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MerchantPaidEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $status;

    public $receipt;

    public $message;

    private $receiptNumber;

    public function __construct($status, $receipt, $receiptNumber, $message)
    {
        $this->status = $status;

        $this->receipt = $receipt;

        $this->message = $message;

        $this->receiptNumber = $receiptNumber;
    }

    public function broadcastOn()
    {

        return new Channel('merchantpayments.'.$this->receiptNumber);
    }
}
