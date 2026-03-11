<?php

namespace Domain\BXBank\MoneyTransfer\Lookup\Factories;

use Domain\BXBank\MoneyTransfer\Lookup\Dtos\LookupDto;
use Domain\BXBank\MoneyTransfer\Lookup\Verifiers\Mfsa;
use Domain\BXBank\MoneyTransfer\Lookup\Strategies\BeneficiaryLookup;

class BeneficiaryLookupFactory
{
    protected static $verifiers = [
        Mfsa::class,
        
    ];

    public static function getVerifier(LookupDto $payload): BeneficiaryLookup
    {
        $verifier = collect(static::$verifiers)
            ->map(fn (string $verifieryClass) => new $verifieryClass($payload))
            ->first(fn (BeneficiaryLookup $verifier) => $verifier->handlesPayload());

        return $verifier ?? new DefaultBeneficiaryLookup($payload);
    }
}