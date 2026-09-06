<?php

namespace Tests\Feature\Auth;

use App\Rules\DeliverableEmail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DeliverableEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The suite disables this globally (phpunit.xml); these tests are the one
        // place it's exercised for real, so turn it back on here.
        config(['mail.verify_deliverability' => true]);

        if (@dns_get_record('gmail.com', DNS_MX) === false) {
            $this->markTestSkipped('No DNS resolver available in this environment.');
        }
    }

    private function fails(string $email): bool
    {
        return Validator::make(
            ['email' => $email],
            ['email' => [new DeliverableEmail]],
        )->fails();
    }

    public function test_it_passes_an_address_on_a_domain_that_accepts_mail(): void
    {
        $this->assertFalse($this->fails('someone@gmail.com'));
    }

    public function test_it_rejects_an_address_on_a_domain_with_no_mail_server(): void
    {
        $this->assertTrue($this->fails('someone@this-domain-does-not-exist-9f3k2.com'));
    }

    public function test_it_ignores_a_malformed_address_and_leaves_that_to_the_email_rule(): void
    {
        $this->assertFalse($this->fails('not-an-email'));
    }

    public function test_it_is_skipped_when_deliverability_checking_is_disabled(): void
    {
        config(['mail.verify_deliverability' => false]);

        $this->assertFalse($this->fails('someone@this-domain-does-not-exist-9f3k2.com'));
    }
}
