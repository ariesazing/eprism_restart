<?php

namespace Tests\Feature;

use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guest "start a research submission" page never persists anything itself — it only
 * stages fields client-side (see resources/js/guest-draft.js) for the authenticated
 * submissions.store endpoint to pick up after registration. These tests cover the
 * server-side half of that contract: what a guest can and can't reach.
 */
class GuestSubmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OrganizationalUnitSeeder::class);
        $this->seed(OrganizationalUnitPositionSeeder::class);
    }

    public function test_guest_can_view_the_start_submission_page(): void
    {
        $response = $this->get(route('guest-submissions.create'));

        $response->assertOk();
        $response->assertSee('Start Your Research Submission');
        $response->assertSee('data-org-unit', false);
    }

    public function test_guest_cannot_access_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_the_repository(): void
    {
        $this->get(route('repository.index'))->assertRedirect(route('login'));
    }

    /**
     * Every protected area of the app redirects a signed-out visitor to /login rather than
     * showing the page (or a bare 403) — confirmed broadly, not just for dashboard/
     * repository above, since this covers every role's routes including admin-only ones
     * like /admin/activity.
     */
    public function test_a_signed_out_visitor_hitting_any_protected_url_is_sent_to_login(): void
    {
        foreach ([
            '/dashboard',
            '/repository',
            '/profile',
            '/submissions',
            '/reviewer/submissions',
            '/admin/users',
            '/admin/activity',
            '/admin/reports',
            '/admin/organizational-units',
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_guest_cannot_bypass_registration_by_posting_directly_to_submissions_store(): void
    {
        $school = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();

        $response = $this->post(route('submissions.store'), [
            'title' => 'Sneaky Submission',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $school->name,
            'school_id' => $school->school_id,
            'proponents' => [
                ['last_name' => 'Doe', 'first_name' => 'Jane', 'position' => 'Teacher I'],
            ],
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('research_submissions', ['title' => 'Sneaky Submission']);
    }

    public function test_welcome_page_offers_the_two_guest_paths(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('guest-submissions.create'), false);
        $response->assertSee(route('login'), false);
    }
}
