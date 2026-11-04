<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\TravelRequest;
use App\Models\TravelRequestRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRequestRecipient>
 */
class TravelRequestRecipientFactory extends Factory
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
            'available_at' => now(),
            'status' => 'active',
        ];
    }
}
