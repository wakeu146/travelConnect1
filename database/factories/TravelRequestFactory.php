<?php

namespace Database\Factories;

use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TravelRequest> */
class TravelRequestFactory extends Factory
{
    protected $model = TravelRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'traveler']),
            'title' => fake()->sentence(5),
            'destination' => fake()->city(),
            'travel_date' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'budget' => fake()->numberBetween(500, 8000),
            'description' => fake()->realTextBetween(100, 240),
            'status' => 'open',
        ];
    }
}