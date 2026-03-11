<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'phone' => $this->faker->e164PhoneNumber,
            'country_code' => $this->faker->randomElement([
                '+233', '+234', '+235',
            ]),
            'status' => $this->faker->randomElement([
                'active', 'inactive', 'blocked',
            ]),
            'password' => Hash::make('password'),
            'onboarding_stage' => $this->faker->randomDigit(),
            'mifos_sync_status' => $this->faker->randomDigit(),
            'mifos_client_id' => $this->faker->randomDigit(),
            'gender' => $this->faker->randomElement([
                'male', 'female',
            ]),
            'is_favorite_merchant' => $this->faker->randomDigit(),
            'mini_app_merchant' => 0,
            'is_admin' => 'no',
            'is_merchant' => 'no',
        ];
    }

    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
            ];
        });
    }

    public function admin()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_admin' => 'yes',
            ];
        });
    }

    public function merchant()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_merchant' => 'yes',
            ];
        });
    }
}
