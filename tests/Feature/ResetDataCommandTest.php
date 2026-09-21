<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\SubmissionDocumentTemplate;
use App\Models\SubmissionWindow;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `eprism:reset-data` — clears everything but the document templates and reseeds.
 */
class ResetDataCommandTest extends TestCase
{
    // Not RefreshDatabase: the command truncates tables with foreign keys switched off, which
    // SQLite ignores inside the transaction that trait wraps every test in. The in-memory
    // database is new for each test (a fresh app), so it just gets migrated here instead.

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
        Storage::fake('local');
        config(['eprism.admin' => ['name' => 'Fresh Admin', 'email' => 'fresh-admin@example.test', 'password' => 'a-good-password']]);
    }

    /**
     * @return array{User, SubmissionDocumentTemplate}
     */
    private function populate(): array
    {
        $researcher = User::factory()->create();
        User::factory()->reviewer()->create();
        $researcher->submissions()->create(['title' => 'Test Data', 'research_type' => 'basic', 'classification' => 'proposal', 'status' => SubmissionStatus::SUBMITTED]);
        SubmissionWindow::forWindow('basic', 'proposal')->update(['is_open' => false, 'memorandum_path' => 'submission-memoranda/memo.pdf']);

        $template = SubmissionDocumentTemplate::create([
            'template_key' => 'basic_proposal',
            'body_html' => '<p>My custom template ${title}</p>',
            'docx_path' => 'onlyoffice-documents/templates/basic_proposal.docx',
            'docx_key' => 'key-1',
            'updated_by' => $researcher->id,
        ]);

        foreach ([
            'research-documents/a.pdf', 'research-photos/p.jpg', 'research-snapshots/1/v1.pdf.enc', 'rapm-documents/1/review-summary/v1.pdf.enc',
            'manuscripts/1/working/x/source.docx', 'manuscripts/tmp/t.docx', 'onlyoffice-tmp/u/filled.docx', 'onlyoffice-transient/t.docx',
            'onlyoffice-documents/5/context.docx', 'submission-memoranda/memo.pdf', 'stray-file-in-root.txt',
            // Kept:
            'onlyoffice-documents/templates/basic_proposal.docx', 'template-images/logo.png', '.gitignore',
        ] as $path) {
            Storage::disk('local')->put($path, 'x');
        }

        return [$researcher, $template];
    }

    public function test_it_clears_everything_but_the_templates_and_their_files(): void
    {
        [, $template] = $this->populate();

        $this->artisan('eprism:reset-data', ['--force' => true])->assertExitCode(0);

        // Only the reseeded admin is left.
        $admin = User::query()->sole();
        $this->assertSame('fresh-admin@example.test', $admin->email);
        $this->assertSame('Fresh Admin', $admin->name);
        $this->assertSame(UserRole::ADMIN, $admin->role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('a-good-password', $admin->password));
        $this->assertSame(1, $admin->id, 'ids restart, so the new admin is id 1');

        $this->assertDatabaseCount('research_submissions', 0);
        $this->assertDatabaseCount('submission_windows', 0);

        // Reference data is back.
        $this->assertGreaterThan(0, OrganizationalUnit::query()->count());
        $this->assertGreaterThan(0, OrganizationalUnitPosition::query()->count());

        // The template row survived untouched, still pointing at its file, and still marked
        // "edited" — by the new admin, since the user who edited it is gone.
        $kept = $template->fresh();
        $this->assertSame('<p>My custom template ${title}</p>', $kept->body_html);
        $this->assertSame('onlyoffice-documents/templates/basic_proposal.docx', $kept->docx_path);
        $this->assertSame('key-1', $kept->docx_key);
        $this->assertSame($admin->id, $kept->updated_by);

        $disk = Storage::disk('local');
        foreach (['onlyoffice-documents/templates/basic_proposal.docx', 'template-images/logo.png', '.gitignore'] as $path) {
            $disk->assertExists($path);
        }
        foreach ([
            'research-documents/a.pdf', 'research-photos/p.jpg', 'research-snapshots/1/v1.pdf.enc', 'rapm-documents/1/review-summary/v1.pdf.enc',
            'manuscripts/1/working/x/source.docx', 'manuscripts/tmp/t.docx', 'onlyoffice-tmp/u/filled.docx', 'onlyoffice-transient/t.docx',
            'onlyoffice-documents/5/context.docx', 'submission-memoranda/memo.pdf', 'stray-file-in-root.txt',
        ] as $path) {
            $disk->assertMissing($path);
        }
    }

    public function test_it_seeds_a_template_that_is_missing_but_never_overwrites_an_existing_one(): void
    {
        $this->populate();
        $custom = SubmissionDocumentTemplate::create(['template_key' => 'review_summary', 'body_html' => '<p>ADMIN EDITED</p>', 'updated_by' => User::query()->first()->id]);

        $this->artisan('eprism:reset-data', ['--force' => true])->assertExitCode(0);

        $this->assertSame('<p>ADMIN EDITED</p>', $custom->fresh()->body_html);
        // The other three chapter templates and the routing slip weren't there, so they were added.
        foreach (['action_proposal', 'action_completed', 'basic_completed', 'routing_slip'] as $key) {
            $this->assertNotNull(SubmissionDocumentTemplate::active($key), "{$key} should have been seeded");
        }
        $this->assertStringContainsString('My custom template', SubmissionDocumentTemplate::active('basic_proposal')->body_html);
    }

    public function test_it_refuses_to_touch_anything_without_a_usable_admin_login(): void
    {
        $this->populate();
        config(['eprism.admin' => ['name' => 'X', 'email' => null, 'password' => null]]);

        $this->artisan('eprism:reset-data', ['--force' => true])
            ->expectsOutputToContain('ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD must all be set')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('research_submissions', 1);
        Storage::disk('local')->assertExists('research-documents/a.pdf');
    }

    public function test_declining_the_confirmation_changes_nothing(): void
    {
        $this->populate();

        $this->artisan('eprism:reset-data')
            ->expectsConfirmation('Continue?', 'no')
            ->expectsOutputToContain('Nothing was changed.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 2);
        Storage::disk('local')->assertExists('research-documents/a.pdf');
    }

    public function test_the_admin_seeder_is_idempotent_and_reads_config_not_env(): void
    {
        $this->seed(AdminUserSeeder::class);
        User::query()->where('email', 'fresh-admin@example.test')->update(['name' => 'Renamed Since']);

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'fresh-admin@example.test')->count());
        $this->assertSame('Renamed Since', User::query()->where('email', 'fresh-admin@example.test')->value('name'));
    }
}
