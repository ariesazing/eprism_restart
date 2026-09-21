<?php

namespace App\Console\Commands;

use App\Models\OrganizationalUnit;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use App\Rapm\RapmTemplateRegistry;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Database\Seeders\RapmTemplateSeeder;
use Database\Seeders\SubmissionDocumentTemplateSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Wipes everything a live system has accumulated — accounts, submissions, reviews, uploaded
 * files, the submission timeline, logs — while keeping the document templates, then reseeds
 * what the app needs to run. For clearing test data before go-live.
 *
 * Kept: the `migrations` table, the document templates (the `submission_document_templates`
 * rows — chapter templates and the two RAPM ones — with their format settings, plus the
 * .docx files they point to and the uploaded `template-images/`). Everything else is emptied.
 *
 * Deliberately NOT `migrate:fresh --seed`: that drops the template rows, and the app then
 * recreates a template with a *blank* .docx over the real file the next time an admin opens it.
 * Files are cleared by keeping a short allow-list rather than deleting a list of known folders,
 * so a folder a newer feature adds is cleaned too instead of silently surviving.
 */
class ResetData extends Command
{
    protected $signature = 'eprism:reset-data {--force : Skip the confirmation prompt (required when not running interactively)}';

    protected $description = 'Delete all data and uploaded files except document templates, then reseed the admin account and reference data';

    private const KEEP_TABLES = ['migrations', 'submission_document_templates'];

    /** Folders (on the local disk) that are kept whole. */
    private const KEEP_DIRECTORIES = ['template-images', 'onlyoffice-documents/templates'];

    public function handle(): int
    {
        // Before anything is deleted: an empty database with no way to sign in is the failure to avoid.
        try {
            $admin = AdminUserSeeder::credentials();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $tables = array_values(array_diff(Schema::getTableListing(schemaQualified: false), self::KEEP_TABLES));

        $this->warn('This permanently deletes ALL data except document templates:');
        $this->line('  users: '.DB::table('users')->count().', submissions: '.DB::table('research_submissions')->count().', plus every review, comment, log, session and queued job');
        $this->line('  uploaded files under '.$disk->path('').' (templates and template-images are kept)');
        $this->line('  kept: '.SubmissionDocumentTemplate::query()->count().' document template(s)');
        $this->line("  reseeded: admin account {$admin['email']}, offices/schools, positions");

        if (! $this->option('force') && ! $this->confirm('Continue?', false)) {
            $this->info('Nothing was changed.');

            return self::SUCCESS;
        }

        $keepFiles = SubmissionDocumentTemplate::query()->whereNotNull('docx_path')->pluck('docx_path')->all();
        $hadEditedTemplates = SubmissionDocumentTemplate::query()->whereNotNull('updated_by')->exists();

        $this->emptyTables($tables);
        $removed = $this->prune('', array_merge(self::KEEP_DIRECTORIES, $keepFiles));

        $this->seed($hadEditedTemplates);

        $this->callSilently('cache:clear');
        $this->callSilently('queue:restart');

        $this->info('Done. '.count($tables)." tables emptied, {$removed} file(s)/folder(s) removed.");
        $this->line('The admin can sign in as '.$admin['email'].'.');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $tables
     */
    private function emptyTables(array $tables): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * @param  list<string>  $keep  folders kept whole, and individual files a template points at
     * @return int how many files and folders were removed
     */
    private function prune(string $directory, array $keep): int
    {
        $disk = Storage::disk('local');
        $removed = 0;

        foreach ($disk->files($directory) as $file) {
            // Dotfiles (.gitignore) belong to the deployment, not to any data.
            if (str_starts_with(basename($file), '.') || in_array($file, $keep, true)) {
                continue;
            }

            $disk->delete($file);
            $removed++;
        }

        foreach ($disk->directories($directory) as $folder) {
            if (in_array($folder, $keep, true)) {
                continue;
            }

            // A folder that only *contains* something kept is walked into, not deleted.
            if (collect($keep)->contains(fn (string $path) => str_starts_with($path, $folder.'/'))) {
                $removed += $this->prune($folder, $keep);

                continue;
            }

            $disk->deleteDirectory($folder);
            $removed++;
        }

        return $removed;
    }

    private function seed(bool $hadEditedTemplates): void
    {
        $this->callSilently('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true]);
        $this->callSilently('db:seed', ['--class' => OrganizationalUnitSeeder::class, '--force' => true]);
        $this->callSilently('db:seed', ['--class' => OrganizationalUnitPositionSeeder::class, '--force' => true]);

        // Only ever adds a template that's missing (never overwrites) — the templates are what this keeps.
        $this->callSilently('db:seed', ['--class' => SubmissionDocumentTemplateSeeder::class, '--force' => true]);

        $rapmMissing = collect(RapmTemplateRegistry::all())->contains(fn ($template) => SubmissionDocumentTemplate::active($template->key) === null);

        if ($rapmMissing) {
            $this->callSilently('db:seed', ['--class' => RapmTemplateSeeder::class, '--force' => true]);
        }

        // A template's `updated_by` marks it as admin-edited (which is what stops RapmTemplateSeeder
        // ever refreshing it), but it pointed at a user this just deleted. Hand it to the new admin
        // rather than leave a dangling id that would later name some unrelated account.
        if ($hadEditedTemplates) {
            $adminId = User::query()->where('email', AdminUserSeeder::credentials()['email'])->value('id');

            SubmissionDocumentTemplate::query()->whereNotNull('updated_by')->update(['updated_by' => $adminId]);
        }

        OrganizationalUnit::forgetCache();
    }
}
