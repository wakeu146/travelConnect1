<?php

namespace Database\Factories;

use App\Enums\PackageStatus;
use App\Models\Agency;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Package> */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(4);

        return [
            'agency_id' => Agency::factory(),
            'slug' => Str::slug($title),
            'title' => $title,
            'summary' => $this->faker->sentence(8),
            'description' => $this->faker->paragraph(),
            'destination' => $this->faker->city(),
            'price_from' => $this->faker->randomFloat(2, 50, 2500),
            'duration_days' => $this->faker->numberBetween(3, 14),
            'status' => PackageStatus::DRAFT->value,
            'published_at' => null,
            'is_featured' => false,
        ];
    }
}
