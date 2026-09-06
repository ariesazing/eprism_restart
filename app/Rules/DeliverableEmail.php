<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Rejects an email address whose domain can't receive mail — a typo'd or made-up
 * domain (jdoe@gmial.com, someone@not-a-real-company.xyz) — so registration doesn't
 * create an account that could never verify.
 *
 * This is a DNS-level check: it confirms the domain publishes a mail server, not that
 * the specific mailbox exists. The mailbox itself is still proven only by the user
 * clicking the verification link. The check is skipped entirely when
 * config('mail.verify_deliverability') is false (the test suite) and when the DNS
 * lookup itself errors out, so a resolver hiccup never blocks a legitimate signup.
 */
class DeliverableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! config('mail.verify_deliverability', true)) {
            return;
        }

        if (! is_string($value) || ! str_contains($value, '@')) {
            return; // The 'email' rule already reports a malformed address.
        }

        $domain = Str::afterLast($value, '@');

        // Look the domain up in its ASCII (punycode) form so internationalised
        // domains resolve.
        if (function_exists('idn_to_ascii') && ($ascii = idn_to_ascii($domain)) !== false) {
            $domain = $ascii;
        }

        $records = @dns_get_record($domain, DNS_MX | DNS_A | DNS_AAAA);

        // false === the lookup failed (no resolver, timeout). Don't punish the user
        // for our infrastructure — let the address through.
        if ($records === false) {
            return;
        }

        $hasMailExchanger = collect($records)
            ->where('type', 'MX')
            ->pluck('target')
            ->map(fn ($target) => rtrim((string) $target, '.'))
            ->filter() // drops the RFC 7505 "null MX" (an empty target = "no mail here")
            ->isNotEmpty();

        // RFC 5321 §5.1: with no usable MX, the domain's own address record acts as
        // the implicit mail exchanger.
        $hasAddressRecord = collect($records)
            ->whereIn('type', ['A', 'AAAA'])
            ->isNotEmpty();

        if (! $hasMailExchanger && ! $hasAddressRecord) {
            $fail("We couldn't find a mail server for that email address. Please check the spelling and try again.");
        }
    }
}
