<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CashOutMethodResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'channel' => $this->channel,
            'fund_type' => $this->fund_type,
            'description' => $this->description,
            'icon_url' => url($this->icon_url),
            'bank_code' => $this->code,
            'supported_currencies' => $this->supported_currencies,
            'active_state' => $this->active_state,
            'charge_code' => $this->charge_code,
        ];
    }
}
