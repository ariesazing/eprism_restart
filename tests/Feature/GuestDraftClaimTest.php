<?php

namespace Tests\Feature;

use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's claim script (resources/views/dashboard.blade.php) sends this exact flat
 * FormData shape to submissions.store after a fresh registration. This test exercises that
 * same contract end-to-end — a real account posting exactly what the guest draft flow
 * would — without needing a browser to run the localStorage/fetch half of it.
 */
class GuestDraftClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OrganizationalUnitSeeder::class);
        $this->seed(OrganizationalUnitPositionSeeder::class);
    }

    public function test_a_newly_registered_researcher_can_claim_their_staged_guest_draft(): void
    {
        $school = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();

        $this->post(route('register'), [
            'name' => 'Fresh Researcher',
            'email' => 'fresh-researcher@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'fresh-researcher@example.com')->firstOrFail();

        // Exactly the FormData keys the dashboard claim script builds from the
        // localStorage-staged draft.
        $response = $this->actingAs($user)->post(route('submissions.store'), [
            'title' => 'A Study Started Before Registering',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $school->name,
            'school_id' => $school->school_id,
            'proponents' => [
                0 => [
                    'last_name' => 'Dela Cruz',
                    'first_name' => 'Juan',
                    'middle_initial' => '',
                    'position' => 'Teacher I',
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertRedirect();

        $submission = $user->submissions()->firstOrFail();
        $this->assertSame('A Study Started Before Registering', $submission->title);
        $this->assertSame($school->name, $submission->organizational_unit);
        $this->assertSame('Dela Cruz', $submission->proponents()->firstOrFail()->last_name);
    }
}
