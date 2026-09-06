<?php

namespace Database\Seeders;

use App\Models\Agency;
use Illuminate\Database\Seeder;

class AgencyExperienceSeeder extends Seeder
{
    public function run(): void
    {
        $gallerySets = [
            'Atlas Horizon Voyages' => [['image' => 'p1.jpeg', 'destination' => 'France'], ['image' => 'p2.jpeg', 'destination' => 'Switzerland'], ['image' => 'p3.jpeg', 'destination' => 'Italy']],
            'Lumiere Routes' => [['image' => 'p2.jpeg', 'destination' => 'Portugal'], ['image' => 'p3.jpeg', 'destination' => 'Spain'], ['image' => 'p4.jpeg', 'destination' => 'Greece']],
            'Northstar Escapes' => [['image' => 'p3.jpeg', 'destination' => 'Canada'], ['image' => 'p4.jpeg', 'destination' => 'Japan'], ['image' => 'p5.jpeg', 'destination' => 'Switzerland']],
            'Sirocco Travel House' => [['image' => 'p4.jpeg', 'destination' => 'Morocco'], ['image' => 'p5.jpeg', 'destination' => 'Spain'], ['image' => 'p6.jpeg', 'destination' => 'Portugal']],
            'Blue Fern Expeditions' => [['image' => 'p5.jpeg', 'destination' => 'New Zealand'], ['image' => 'p6.jpeg', 'destination' => 'Australia'], ['image' => 'p7.jpeg', 'destination' => 'Fiji']],
        ];

        Agency::query()->each(function (Agency $agency) use ($gallerySets): void {
            $agency->experiences()->delete();

            foreach ($gallerySets[$agency->company_name] ?? [['image' => 'p1.jpeg', 'destination' => 'France'], ['image' => 'p2.jpeg', 'destination' => 'Spain'], ['image' => 'p3.jpeg', 'destination' => 'Italy']] as $index => $experience) {
                $agency->experiences()->create([
                    'image_path' => 'images/traveler_experience/'.$experience['image'],
                    'destination' => $experience['destination'],
                    'caption' => [
                        'A traveler beginning a new journey.',
                        'A shared trip made memorable.',
                        'A real traveler experience on the move.',
                    ][$index],
                    'alt_text' => 'Traveler experience shared by '.$agency->company_name,
                    'published_at' => now()->subDays(3 - $index),
                ]);
            }
        });
    }
}
