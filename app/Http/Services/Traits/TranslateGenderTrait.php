<?php

namespace App\Http\Services\Traits;

trait TranslateGenderTrait
{
    public function translateGender($gender)
    {
        $gender = strtoupper( $gender );

        $map = [
            'MALE' => 1,
            'FEMALE' => 2,
        ];

        return $map[$gender] ?? null;
    }

}