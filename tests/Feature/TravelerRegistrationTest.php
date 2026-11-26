<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_traveler_registration_redirects_to_the_traveler_dashboard(): void
    {
        $response = $this->post(route('register.traveler.store'), [
            'name' => 'Nadia Stone',
            'email' => 'nadia@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('auth.success'))
            ->assertSessionHas('success_redirect', route('dashboard'));

        $this->assertAuthenticatedAs(User::query()->where('email', 'nadia@example.com')->firstOrFail());
    }
}
