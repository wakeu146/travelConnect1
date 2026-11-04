<?php

namespace Database\Factories;

use App\Models\TravelRequestProposal;
use App\Models\TravelRequestRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRequestRevision>
 */
class TravelRequestRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'travel_request_proposal_id' => TravelRequestProposal::factory(),
            'actor_id' => User::factory(),
            'snapshot' => ['price' => '1500.00', 'currency' => config('travelconnect.currency')],
        ];
    }
}
