<?php

declare(strict_types=1);

namespace App\Types;

use App\Exceptions\NegativeAmountException;

final class Money
{
    private float $amount;

    public function __construct(
        float $amount
    ) {
        $floatMoney = (float) $amount;

        if ($floatMoney < 0) {
            throw new NegativeAmountException('Money cannot be a negative value.');
        }

        $this->amount = $amount;
    }

    public function percent(float $percentage): self
    {
        $value = $percentage / 100;

        return new self($value * $this->amount);
    }

    public function add(Money $money): self
    {
        $sum = $this->amount + $money->tofloat();

        return new self($sum);
    }

    public function sub(Money $money): self
    {
        $sum = $this->amount - $money->tofloat();

        return new self($sum);
    }

    public function tofloat(): float
    {
        return (float) $this->amount;
    }

    public function tostring(): string
    {
        return makeStringMoney($this->amount);
    }

    public function totwodp(): string
    {
        return totwodp($this->amount);
    }
}
