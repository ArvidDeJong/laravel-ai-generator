<?php

use Illuminate\Support\Facades\Http;

test('offline, the status shows every driver without contacting a provider', function () {
    Http::preventStrayRequests();
    Http::fake();

    $this->artisan('ai-generator:status', ['--offline' => true])
        ->expectsOutputToContain('claude-sonnet-5')
        ->expectsOutputToContain('Default driver')
        ->assertSuccessful();

    Http::assertNothingSent();
});

test('a refused key makes the status fail', function () {
    Http::fake(['api.openai.com/v1/models' => Http::response([], 401)]);

    $this->artisan('ai-generator:status')
        ->expectsOutputToContain('OpenAI refused the API key (HTTP 401).')
        ->assertFailed();
});
