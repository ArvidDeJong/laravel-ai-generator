---
title: "Custom drivers"
nav_order: 8
description: "Add an AI provider the package does not ship: implement the AiContentDriver contract, keep its settings in config/services.php and register it with extend()."
---

# Custom drivers

A driver is the class that talks to the AI provider. The package ships four: OpenAI, Claude
(Anthropic), Gemini and Grok (xAI); see [Multiple providers](providers.md). This page is for any
other provider, for example a local model or a provider of your own. You write a class with one
method and register it under a name.

## What a driver has to do

Your class implements `Darvis\LaravelAiGenerator\Contracts\AiContentDriver`:

```php
public function generate(ContentRequest $request): ContentResult;
```

- The request arrives with the defaults filled in: `language`, `tone`, `readingLevel` and `maxWords`
  are never `null`, `imageStyle` and `imageAspect` neither.
- Return a `ContentResult` with `title`, `intro`, `text`, `seoTitle` and `seoDescription`. The
  package trims them afterwards.
- Throw a `RuntimeException` when the text fails, so calling code can handle your driver the same
  way as the OpenAI driver.
- The image is your driver's job too. The package does not make an image inside `generate()` for a
  custom driver, so `includeImage` does nothing unless your driver acts on it. When an image fails,
  return the text with `errorMessage` filled instead of throwing. `generateImage()` does work: it
  uses OpenAI, or the driver in `AI_GENERATOR_IMAGE_DRIVER`.

## Step 1: write the driver

The provider below, "Acme AI", does not exist. Its URL, its payload and the shape of its answer are
made up. Replace them with what the documentation of your provider says.

**`app/Services/AiDrivers/AcmeAiDriver.php`**

```php
<?php

namespace App\Services\AiDrivers;

use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AcmeAiDriver implements AiContentDriver
{
    public function generate(ContentRequest $request): ContentResult
    {
        $key = config('services.acme_ai.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('ACME_AI_KEY is not set.');
        }

        try {
            // URL, payload and response shape are made up: use the ones from your provider's documentation.
            $response = Http::timeout(60)
                ->withToken($key)
                ->acceptJson()
                ->post(config('services.acme_ai.url').'/generate', [
                    'model' => config('services.acme_ai.model'),
                    'prompt' => $this->prompt($request),
                ])
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new RuntimeException('Acme AI request failed: '.$e->getMessage(), 0, $e);
        }

        $fields = json_decode((string) $response->json('output'), true);

        if (! is_array($fields)) {
            throw new RuntimeException('Acme AI did not return JSON.');
        }

        return new ContentResult(
            title: (string) ($fields['title'] ?? ''),
            intro: (string) ($fields['intro'] ?? ''),
            text: (string) ($fields['text'] ?? ''),
            seoTitle: (string) ($fields['seo_title'] ?? ''),
            seoDescription: (string) ($fields['seo_description'] ?? ''),
        );
    }

    private function prompt(ContentRequest $request): string
    {
        $keywords = implode(', ', $request->keywords ?? []);

        return <<<PROMPT
        Write an article about "{$request->topic}".
        Language: {$request->language}. Tone: {$request->tone}. Reading level: {$request->readingLevel}.
        At most {$request->maxWords} words. Keywords: {$keywords}.
        Return only a JSON object with the keys title, intro, text (HTML with h2, p, ul and li),
        seo_title (at most 60 characters) and seo_description (at most 155 characters).
        PROMPT;
    }
}
```

The driver sends one request, reads the JSON the model wrote and maps it to a `ContentResult`. Every
failure becomes a `RuntimeException`.

## Step 2: keep the driver's settings in your own config

The settings of your driver belong to your application, not to `config/ai-generator.php`. Laravel's
`config/services.php` is the usual place for the credentials of an external service.

**`config/services.php`**

```php
'acme_ai' => [
    'key' => env('ACME_AI_KEY'),
    'model' => env('ACME_AI_MODEL'),
    'url' => env('ACME_AI_URL'),
],
```

**`.env`**

```env
AI_GENERATOR_DRIVER=acme
ACME_AI_KEY=your-api-key-here
ACME_AI_MODEL=the-model-name-from-your-provider
ACME_AI_URL=https://api.your-provider.example/v1
```

## Step 3: register the driver under a name

Register the driver with the driver manager of the package, in the `boot()` method of a service
provider of your application, for example `AppServiceProvider`:

**`app/Providers/AppServiceProvider.php`**

```php
use App\Services\AiDrivers\AcmeAiDriver;
use Darvis\LaravelAiGenerator\AiGeneratorManager;

public function boot(): void
{
    $this->app->make(AiGeneratorManager::class)
        ->extend('acme', fn ($app, ?string $model) => new AcmeAiDriver);
}
```

The closure gets the container and the model asked for with `using()`, or `null`. Now the name
`acme` works everywhere a driver name does:

- `AI_GENERATOR_DRIVER=acme` in `.env` makes it the default.
- `AiGenerator::using('acme')->generate($request)` uses it for one call.
- `AI_GENERATOR_FALLBACKS=acme` lets it take over when the default driver fails.

A driver registered with `extend()` never needs an API key check by the package; your driver checks
its own key.

### Or replace the binding

Earlier versions of the package had no driver manager, and the way to use your own driver was to
replace the binding of the interface. That still works. A binding tells Laravel's service container
which class to build when code asks for an interface. Bind your own class in a service provider of
your application:

**`app/Providers/AiDriverServiceProvider.php`**

```php
<?php

namespace App\Providers;

use App\Services\AiDrivers\AcmeAiDriver;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Illuminate\Support\ServiceProvider;

class AiDriverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (AiGeneratorConfig::driver() === 'acme') {
            $this->app->singleton(AiContentDriver::class, fn () => new AcmeAiDriver);
        }
    }
}
```

Add the provider to **`bootstrap/providers.php`** when it is not listed there yet. An application
that was upgraded from an older Laravel may list its providers in `config/app.php` instead.

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AiDriverServiceProvider::class,
];
```

Providers of your application register after the providers of packages, so your binding replaces
the one of the package. With `AI_GENERATOR_DRIVER=openai` the `if` is false and the OpenAI driver
stays in place, which lets you switch back in `.env`.

This way only the default driver is yours: `using('acme')` and `AI_GENERATOR_FALLBACKS` don't know
the name `acme`. Use the manager's `extend()` when you need those.

### Don't use the container's extend()

`$this->app->extend(AiContentDriver::class, ...)`, the `extend()` of Laravel's container, does not
work here. It first builds the binding of the package, and that one throws `Unsupported AI driver: acme` on a name it does not know,
before your code runs. Use the manager's `extend()` or replace the binding, as above.

## Step 4: check that your driver is used

This does not call any API:

```bash
php artisan tinker --execute="echo get_class(app(\Darvis\LaravelAiGenerator\Contracts\AiContentDriver::class)), PHP_EOL;"
```

With `AI_GENERATOR_DRIVER=acme` you should see `App\Services\AiDrivers\AcmeAiDriver`. When you see
`Unsupported AI driver: acme` instead, the code that registers your driver did not run, or the name
you registered differs from `AI_GENERATOR_DRIVER`.

## Images with a custom driver

`AiGenerator::generateImage()` does not go through your text driver. It uses the image driver:
`AI_GENERATOR_IMAGE_DRIVER` when you set it (`openai`, `gemini` or `xai`), or else OpenAI with
`OPENAI_API_KEY` and `OPENAI_IMAGE_MODEL`. Without a key it returns an `error`, for example
`['error' => 'OPENAI_API_KEY is not set.']`.

To add an image to a text from your own driver, you can call it yourself after `generate()`:

```php
use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;

$generator = app(AiGenerator::class);

$result = $generator->generate(new ContentRequest(topic: 'Your topic'));
$image = $generator->generateImage($result->imagePrompt ?? 'A short description of the image, in English');
```

A driver that can make images itself may also implement
`Darvis\LaravelAiGenerator\Contracts\AiImageDriver`; see the [API reference](api-reference.md#aiimagedriver).

## Test your driver

Fake the HTTP call, so the test never reaches your provider. [Testing](testing.md) shows how.
