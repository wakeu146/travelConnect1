<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\TravelRequest;
use App\Models\TravelRequestProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRequestProposal>
 */
class TravelRequestProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'travel_request_id' => TravelRequest::factory(),
            'agency_id' => Agency::factory(),
            'owner_id' => User::factory()->state(['role' => 'agency_owner']),
            'reference' => 'TC-QUO-'.fake()->unique()->numerify('########'),
            'status' => 'submitted',
            'price' => fake()->numberBetween(1000, 100000),
            'currency' => config('travelconnect.currency'),
            'services_included' => ['Accommodation'],
            'services_excluded' => [],
            'message' => fake()->paragraph(),
            'submitted_at' => now(),
        ];
    }
}
