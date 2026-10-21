<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\Agency;
use App\Models\AgencyDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneUnsubmittedAgenciesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_agency_without_documents_at_twelve_hours_and_disables_owner_account(): void
    {
        Storage::fake('public');
        $this->travelTo(now()->startOfSecond());
        $agency = Agency::factory()->create([
            'created_at' => now()->subHours(12),
            'updated_at' => now()->subHours(12),
            'logo_path' => 'agency-logos/abandoned/logo.png',
        ]);
        $owner = $agency->owner;
        Storage::disk('public')->put($agency->logo_path, 'agency logo');

        $this->artisan('agencies:prune-unsubmitted')
            ->expectsOutput('Deleted 1 abandoned agency account(s).')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('agencies', ['id' => $agency->id]);
        $this->assertSoftDeleted('users', ['id' => $owner->id]);
        Storage::disk('public')->assertMissing($agency->logo_path);
    }

    public function test_keeps_agency_without_documents_until_it_is_twelve_hours_old(): void
    {
        $agency = Agency::factory()->create([
            'created_at' => now()->subHours(11)->subMinutes(59),
            'updated_at' => now()->subHours(11)->subMinutes(59),
        ]);

        $this->artisan('agencies:prune-unsubmitted')->assertExitCode(0);

        $this->assertDatabaseHas('agencies', ['id' => $agency->id]);
        $this->assertDatabaseHas('users', ['id' => $agency->user_id, 'deleted_at' => null]);
    }

    public function test_keeps_agency_that_has_submitted_documents_even_if_older_than_twelve_hours(): void
    {
        $agency = Agency::factory()->create([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        AgencyDocument::factory()->for($agency)->create();

        $this->artisan('agencies:prune-unsubmitted')->assertExitCode(0);

        $this->assertDatabaseHas('agencies', ['id' => $agency->id]);
        $this->assertDatabaseHas('users', ['id' => $agency->user_id, 'deleted_at' => null]);
    }

    public function test_keeps_verified_agency_without_documents_even_if_older_than_twelve_hours(): void
    {
        $agency = Agency::factory()->create([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
            'verification_status' => VerificationStatus::VERIFIED,
        ]);

        $this->artisan('agencies:prune-unsubmitted')->assertExitCode(0);

        $this->assertDatabaseHas('agencies', ['id' => $agency->id]);
        $this->assertDatabaseHas('users', ['id' => $agency->user_id, 'deleted_at' => null]);
    }
}
