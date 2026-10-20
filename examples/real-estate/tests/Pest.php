<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Honeypot\Honeypot;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * The honeypot fields every form post needs (spatie/laravel-honeypot), as the form component prints them.
 *
 * @return array<string, string>
 */
function honeypot(): array
{
    config(['honeypot.amount_of_seconds' => 0]);
    $honeypot = app(Honeypot::class);

    return [$honeypot->nameFieldName() => '', $honeypot->validFromFieldName() => $honeypot->encryptedValidFrom()];
}
