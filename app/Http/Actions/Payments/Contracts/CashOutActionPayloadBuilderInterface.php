<?php

namespace App\Http\Actions\Payments\Contracts;

interface CashOutActionPayloadBuilderInterface
{
    public static function validate();

    public static function build(): array;
}
