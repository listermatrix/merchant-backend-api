<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentReceiptResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'receipt_number' => $this->number,
            'quantity' => $this->quantity,
            'total' => $this->order_total,
            'description' => $this->description,
            'created_at' => $this->created_at,
        ];
    }
}
