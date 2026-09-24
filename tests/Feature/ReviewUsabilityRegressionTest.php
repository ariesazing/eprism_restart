<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewUsabilityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_appear_first_and_date_sort_can_be_reversed(): void
    {
        $admin = User::factory()->admin()->create(['created_at' => now()->subDays(2)]);
        $this->actingAs($admin)->from(route('admin.users.index', ['page' => 2, 'search' => 'missing']))
            ->post(route('admin.users.store'), [
                'name' => 'Z New Account', 'email' => 'new@example.com', 'role' => 'researcher',
                'password' => 'Password1!', 'password_confirmation' => 'Password1!',
            ])->assertRedirect(route('admin.users.index'))->assertSessionDoesntHaveErrors();

        $this->assertNotNull(User::where('email', 'new@example.com')->firstOrFail()->email_verified_at);
        $this->get(route('admin.users.index'))
            ->assertViewHas('users', fn ($users) => $users->first()->email === 'new@example.com')
            ->assertSee('Page 1 of 1');
        $this->get(route('admin.users.index', ['sort' => 'oldest']))
            ->assertViewHas('users', fn ($users) => $users->first()->id === $admin->id);
    }

    public function test_a_unit_is_saved_individually_and_cannot_overwrite_a_sibling(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = OrganizationalUnit::create(['name' => 'One', 'organizational_unit_type' => 'school', 'is_active' => true]);
        $other = OrganizationalUnit::create(['name' => 'Two', 'organizational_unit_type' => 'school', 'is_active' => true]);
        $this->actingAs($admin)->patch(route('admin.organizational-units.update', $unit), [
            'name' => 'Edited', 'school_id' => '12345', 'is_active' => false,
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame('Edited', $unit->fresh()->name);
        $this->assertSame('Two', $other->fresh()->name);
        $this->patch(route('admin.organizational-units.update', $unit), [
            'name' => 'Two', 'is_active' => true,
        ])->assertSessionHasErrorsIn('editUnit'.$unit->id, 'name');
        $this->actingAs(User::factory()->create())->patch(route('admin.organizational-units.update', $unit), [
            'name' => 'Unauthorized', 'is_active' => true,
        ])->assertForbidden();
    }

    public function test_repository_filters_by_school_and_category_without_expanding_researcher_scope(): void
    {
        $researcher = User::factory()->create();
        foreach (['School A', 'School B'] as $school) {
            $researcher->submissions()->create([
                'title' => $school.' study', 'research_type' => 'basic', 'classification' => 'completed',
                'organizational_unit' => $school, 'organizational_unit_type' => 'school',
                'status' => SubmissionStatus::DRAFT, 'proposal_approved_at' => now(),
            ]);
        }
        User::factory()->create()->submissions()->create([
            'title' => 'Private other study', 'research_type' => 'basic', 'classification' => 'completed',
            'organizational_unit' => 'School A', 'organizational_unit_type' => 'school',
            'status' => SubmissionStatus::DRAFT, 'proposal_approved_at' => now(),
        ]);
        $this->actingAs($researcher)->get(route('repository.index', ['organizational_unit' => 'School A', 'unit_type' => 'school']))
            ->assertOk()->assertSee('School A study')->assertDontSee('School B study')->assertDontSee('Private other study');
        $this->get(route('repository.index', ['unit_type' => 'non_school']))
            ->assertViewHas('approvedProposals', fn ($items) => $items->isEmpty());
    }

    public function test_login_errors_never_echo_passwords_or_flash_tokens_and_auth_pages_are_not_cached(): void
    {
        $this->get(route('login'))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $response = $this->postJson(route('login'), [
            'email' => 'absent@example.com', 'password' => 'DoNotEchoThis1!', '_token' => 'DoNotFlashThis',
        ]);
        $response->assertUnprocessable()->assertDontSee('DoNotEchoThis1!')->assertDontSee('DoNotFlashThis');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->from(route('login'))->post(route('login'), [
            'email' => 'absent@example.com', 'password' => 'DoNotEchoThis1!', '_token' => 'DoNotFlashThis',
        ])->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input._token');
    }

    public function test_invalid_search_payload_is_rejected_and_logout_does_not_return_credentials(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->getJson(route('admin.users.index', ['search' => ['invalid']]))
            ->assertUnprocessable()->assertJsonValidationErrors('search');
        $response = $this->post(route('logout'));
        $response->assertRedirect('/')->assertDontSee($admin->password);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertGuest();
    }
}
