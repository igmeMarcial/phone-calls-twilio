<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\PhoneNumber;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PhoneNumber>
 */
class PhoneNumberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = PhoneNumber::class;
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'number' => $this->faker->phoneNumber,
            'verified_at' => now(),
        ];
    }
}
