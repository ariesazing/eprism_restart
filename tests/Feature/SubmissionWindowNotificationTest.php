<?php

namespace Tests\Feature;

use App\Models\SubmissionWindow;
use App\Models\User;
use App\Notifications\SubmissionWindowChanged;
use App\Services\SubmissionWindowNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubmissionWindowNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_proponents_without_accounts_receive_each_transition_once_and_account_addresses_are_deduplicated(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $submission = $user->submissions()->create(['title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal', 'status' => 'draft']);
        foreach (['coauthor@example.test', 'COAUTHOR@example.test', $user->email] as $email) {
            $submission->proponents()->create(['first_name' => 'Co', 'last_name' => 'Author', 'email' => $email, 'position' => 'Teacher']);
        }
        $window = SubmissionWindow::forWindow('action', 'completed');
        $notifier = app(SubmissionWindowNotifier::class);
        $notifier->check($window);
        $window->update(['is_open' => false]);
        $notifier->check($window);
        $notifier->check($window);
        Notification::assertSentTo($user, SubmissionWindowChanged::class);
        Notification::assertSentOnDemand(SubmissionWindowChanged::class, function ($notification, $channels, $notifiable) {
            $this->assertSame('Hello,', $notification->toMail($notifiable)->greeting);

            return isset($notifiable->routes['mail']['coauthor@example.test']) && ! $notification->open;
        });
        Notification::assertCount(2);
        $window->update(['is_open' => true]);
        $notifier->check($window);
        Notification::assertCount(4);
    }

    public function test_manual_changes_email_every_user_once_and_noop_saves_do_not_email(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $users = User::factory()->count(3)->create()->push($admin);
        $payload = [];
        foreach (['basic', 'action'] as $type) {
            foreach (['proposal', 'completed'] as $classification) {
                SubmissionWindow::forWindow($type, $classification);
                $payload['windows'][$type][$classification] = ['is_open' => true];
            }
        }
        $payload['windows']['basic']['proposal']['is_open'] = false;
        $this->actingAs($admin)->patch(route('admin.submission-timeline.update'), $payload)->assertSessionDoesntHaveErrors();
        foreach ($users as $user) {
            Notification::assertSentTo($user, SubmissionWindowChanged::class, fn ($notification) => ! $notification->open);
        }
        Notification::assertCount(4);
        $this->patch(route('admin.submission-timeline.update'), $payload)->assertSessionDoesntHaveErrors();
        Notification::assertCount(4);
        $payload['windows']['basic']['proposal']['is_open'] = true;
        $this->patch(route('admin.submission-timeline.update'), $payload)->assertSessionDoesntHaveErrors();
        Notification::assertCount(8);
    }

    public function test_scheduled_opening_and_closing_are_detected_without_duplicate_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->travelTo(now()->startOfDay());
        $window = SubmissionWindow::forWindow('basic', 'proposal');
        $window->update(['opens_at' => now()->addHour(), 'closes_at' => now()->addHours(2)]);
        $notifier = app(SubmissionWindowNotifier::class);
        $notifier->check($window);
        Notification::assertNothingSent();
        $this->travel(61)->minutes();
        $notifier->checkAll();
        $notifier->checkAll();
        Notification::assertSentTo($user, SubmissionWindowChanged::class, fn ($notification) => $notification->open);
        Notification::assertCount(1);
        $this->travel(60)->minutes();
        $notifier->checkAll();
        Notification::assertSentTo($user, SubmissionWindowChanged::class, fn ($notification) => ! $notification->open);
        Notification::assertCount(2);
    }
}
