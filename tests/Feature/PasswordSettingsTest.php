<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_traveler_can_update_their_password_from_account_settings(): void
    {
        $traveler = User::factory()->create([
            'role' => UserRole::TRAVELER,
            'password' => 'old-password',
        ]);
        $settings = $this->actingAs($traveler)->get(route('settings'));

        $settings->assertSee('name="password"', false);
        $settings->assertSee('name="password_confirmation"', false);

        $response = $this->put(route('settings.password'), [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('password_status', __('messages.password_updated_successfully'));
        $this->assertTrue(Hash::check('new-password123', $traveler->fresh()->password));
        $this->assertFalse(Hash::check('old-password', $traveler->fresh()->password));
    }

    public function test_agency_owner_can_update_their_password_from_agency_settings(): void
    {
        $agency = Agency::factory()->create();
        $owner = $agency->owner;
        $settings = $this->actingAs($owner)->get(route('agency.profile'));

        $settings->assertSee('name="password"', false);
        $settings->assertSee('name="password_confirmation"', false);

        $response = $this->put(route('settings.password'), [
            'password' => 'agency-new-password',
            'password_confirmation' => 'agency-new-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('password_status', __('messages.password_updated_successfully'));
        $this->assertTrue(Hash::check('agency-new-password', $owner->fresh()->password));
        $this->assertSame(UserRole::AGENCY_OWNER, $owner->fresh()->role);
    }

    public function test_password_confirmation_must_match_and_preserves_the_current_password(): void
    {
        $traveler = User::factory()->create([
            'role' => UserRole::TRAVELER,
            'password' => 'old-password',
        ]);
        $storedPassword = $traveler->password;

        $response = $this->actingAs($traveler)->from(route('settings'))->put(route('settings.password'), [
            'password' => 'new-password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertRedirect(route('settings'));
        $response->assertSessionHasErrors('password');
        $this->assertSame($storedPassword, $traveler->fresh()->password);
    }

    public function test_password_update_requires_authentication(): void
    {
        $response = $this->put(route('settings.password'), [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('login'));
    }
}
