<?php

namespace App\Http\Actions\Payments\Contracts;

interface GetUrl
{
    /** The  url to send the cashout request to*/
    public function getUrl(): string;
}
