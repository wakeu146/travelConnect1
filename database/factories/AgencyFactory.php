<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Agency> */
class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'agency_owner']),
            'company_name' => fake()->company().' Travel',
            'description' => fake()->sentence(18),
            'license_number' => fake()->unique()->bothify('TA-####-????'),
            'verification_status' => 'pending',
            'trust_score' => 0,
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'website' => fake()->url(),
        ];
    }
}