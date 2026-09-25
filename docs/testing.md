---
title: "Testing"
nav_order: 9
description: "Test Laravel code that uses darvis/laravel-ai-generator without calling a real AI API: bind a fake driver, or fake the HTTP calls of OpenAI, Claude, Gemini or Grok."
---

# Testing

A test must never call a real AI provider: it costs money, it is slow and the answer differs on
every run.
There are two ways to avoid it. Pick the first one unless you have a reason for the second.

The examples use [Pest](https://pestphp.com). In a PHPUnit test class the same lines go inside a test
method.

## Bind a fake driver when you test your own code

Your code asks the package for a text; what matters in the test is what your code does with the
answer. Replace the driver with one that returns a fixed result.

This test covers the `article:generate` command from [Usage](usage.md).

**`tests/Feature/GenerateArticleTest.php`**

```php
<?php

use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;

test('the command prints the generated article', function () {
    $this->app->singleton(AiContentDriver::class, fn () => new class implements AiContentDriver
    {
        public function generate(ContentRequest $request): ContentResult
        {
            return new ContentResult(
                title: 'A fake title',
                intro: 'A fake intro.',
                text: '<p>Fake text about '.$request->topic.'</p>',
                seoTitle: 'Fake SEO title',
                seoDescription: 'Fake meta description',
            );
        }
    });

    $this->artisan('article:generate', ['topic' => 'bicycles'])
        ->expectsOutput('A fake title')
        ->expectsOutput('<p>Fake text about bicycles</p>')
        ->assertExitCode(0);
});
```

The anonymous class takes the place of the configured driver, so `generate()` returns the fixed result
and no request leaves the machine. The test needs no API key.

Bind the fake **before** anything resolves `AiGenerator`. The generator is a singleton that receives
its driver when it is first built; a driver you bind after that is not used.

### Test the failure paths too

Let the fake throw, or return a result with an error, to test how your code reacts:

```php
// The text fails
throw new RuntimeException('OpenAI request failed: test');

// The text succeeds, the image fails
return new ContentResult(
    title: 'A fake title',
    intro: 'A fake intro.',
    text: '<p>Fake text</p>',
    seoTitle: 'Fake SEO title',
    seoDescription: 'Fake meta description',
    imagePrompt: 'A box on a table',
    errorMessage: 'OpenAI Image Error: test',
);
```

## Fake the HTTP calls when the OpenAI driver has to run

Use this when the test is about the request itself, for example which model or prompt is sent.
Laravel's [`Http::fake()`](https://laravel.com/docs/http-client#testing) answers the requests
instead of OpenAI.

Two things are specific to this package:

- Set an API key in the test. Without one the driver throws `OPENAI_API_KEY is not set.` before it
  sends anything.
- The text answer has the shape of the OpenAI Responses API: the driver reads the first
  `output[].content[]` item with `type` `output_text`, and its `text` is a JSON string with the six
  fields below.

**`tests/Feature/OpenAiRequestTest.php`**

```php
<?php

use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('an article with an image takes two requests', function () {
    config()->set('ai-generator.drivers.openai.api_key', 'test-key');

    Http::preventStrayRequests();

    Http::fake([
        '*/responses' => Http::response(['output' => [['content' => [[
            'type' => 'output_text',
            'text' => json_encode([
                'title' => 'A fake title',
                'intro' => 'A fake intro.',
                'text' => '<p>Fake text</p>',
                'seo_title' => 'Fake SEO title',
                'seo_description' => 'Fake meta description',
                'image_prompt' => 'A box on a table',
            ]),
        ]]]]]),
        '*/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('png')]]]),
    ]);

    $result = AiGenerator::generate(new ContentRequest(topic: 'Packaging', language: 'en'));

    expect($result->title)->toBe('A fake title')
        ->and($result->hasImage())->toBeTrue()
        ->and($result->hasError())->toBeFalse();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/responses')
        && $request['model'] === 'gpt-4.1-mini');
});
```

`Http::preventStrayRequests()` makes a request without a fake fail the test, instead of reaching
OpenAI. With `includeImage: false` only the `*/responses` fake is used and one request is sent.

To test a failing image, answer it with an error status:

```php
'*/images/generations' => Http::response([], 500),
```

The result then has the text, `hasError()` is `true` and `errorMessage` starts with
`OpenAI Image Error:`. The driver tries a failing request twice with 250 milliseconds in between, so
such a test takes a little longer.

`generateImage()` only needs the `*/images/generations` fake, and also an API key.

## Fake Claude, Gemini or Grok

The other drivers work the same way: set their key, fake their URL and answer in the shape of their
API. The JSON string with the six fields goes where each provider puts its text.

```php
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;
use Illuminate\Support\Facades\Http;

$fields = json_encode([
    'title' => 'A fake title',
    'intro' => 'A fake intro.',
    'text' => '<p>Fake text</p>',
    'seo_title' => 'Fake SEO title',
    'seo_description' => 'Fake meta description',
    'image_prompt' => 'A box on a table',
]);

config()->set('ai-generator.drivers.anthropic.api_key', 'test-key');
config()->set('ai-generator.drivers.gemini.api_key', 'test-key');
config()->set('ai-generator.drivers.xai.api_key', 'test-key');

Http::preventStrayRequests();

Http::fake([
    // Claude: the Messages API, the text is in a content block of type "text"
    'api.anthropic.com/v1/messages' => Http::response([
        'content' => [['type' => 'text', 'text' => $fields]],
        'stop_reason' => 'end_turn',
    ]),

    // Gemini: generateContent, the text is in candidates[0].content.parts
    'generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => $fields]]], 'finishReason' => 'STOP']],
    ]),

    // Grok: chat completions, the text is the message content
    'api.x.ai/v1/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => $fields]]],
    ]),
]);

$result = AiGenerator::using('anthropic')->generate(new ContentRequest(topic: 'Packaging', includeImage: false));
```

For images:

- **Gemini** answers its image model on the same `generateContent` URL, with the image in a part
  `['inlineData' => ['mimeType' => 'image/png', 'data' => '<base64>']]`.
- **Grok** answers `api.x.ai/v1/images/generations` with `['data' => [['b64_json' => '<base64>']]]`.
- **Claude** makes no images: with `anthropic` as text driver the image request goes to OpenAI, so
  fake `*/images/generations` of OpenAI and set `OPENAI_API_KEY` in the test.

## Test a custom driver

Fake the URL of your provider the same way, bind your driver and call `generate()`. The example
matches the made up provider on [Custom drivers](custom-drivers.md).

**`tests/Feature/AcmeAiDriverTest.php`**

```php
<?php

use App\Services\AiDrivers\AcmeAiDriver;
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;
use Illuminate\Support\Facades\Http;

test('the Acme AI driver maps the answer to a result', function () {
    config()->set('services.acme_ai', [
        'key' => 'test-key',
        'model' => 'test-model',
        'url' => 'https://api.your-provider.example/v1',
    ]);

    $this->app->singleton(AiContentDriver::class, fn () => new AcmeAiDriver);

    Http::preventStrayRequests();
    Http::fake([
        'api.your-provider.example/*' => Http::response(['output' => json_encode([
            'title' => 'A fake title',
            'intro' => 'A fake intro.',
            'text' => '<p>Fake text</p>',
            'seo_title' => 'Fake SEO title',
            'seo_description' => 'Fake meta description',
        ])]),
    ]);

    $result = AiGenerator::generate(new ContentRequest(topic: 'Packaging'));

    expect($result->title)->toBe('A fake title');
});
```
