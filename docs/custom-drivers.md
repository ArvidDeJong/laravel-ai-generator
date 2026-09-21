---
title: "Custom drivers"
nav_order: 5
description: "Let another AI provider write the text: implement the AiContentDriver contract, keep its settings in config/services.php and replace the container binding."
---

# Custom drivers

A driver is the class that talks to the AI provider. The package ships one, for OpenAI. To let
another provider write the text, you write a class with one method and tell Laravel to use it.

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
- The image is your driver's job too. The package does not make an image for a custom driver, so
  `includeImage` does nothing unless your driver acts on it. When an image fails, return the text
  with `errorMessage` filled instead of throwing.

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

## Step 3: replace the binding

A binding tells Laravel's service container which class to build when code asks for an interface.
The package binds `AiContentDriver` to its OpenAI driver. Bind your own class in a service provider
of your application, and the package uses yours.

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

### Don't use extend()

`$this->app->extend(AiContentDriver::class, ...)` does not work here. `extend()` first builds the
binding of the package, and that one throws `Unsupported AI driver: acme` on a name it does not know,
before your code runs. Replace the binding, as above.

## Step 4: check that your driver is used

This does not call any API:

```bash
php artisan tinker --execute="echo get_class(app(\Darvis\LaravelAiGenerator\Contracts\AiContentDriver::class)), PHP_EOL;"
```

You should see `App\Services\AiDrivers\AcmeAiDriver`. When you see `Unsupported AI driver: acme`
instead, your provider is not registered or the name in the `if` differs from
`AI_GENERATOR_DRIVER`.

## generateImage() still calls OpenAI

`AiGenerator::generateImage()` does not go through the driver. It always calls the OpenAI image API
with `OPENAI_API_KEY` and `OPENAI_IMAGE_MODEL`, whatever driver writes the text. Without an OpenAI
key it returns `['error' => 'OPENAI_API_KEY is not set.']`.

To add an image to a text from your own driver, you can call it yourself after `generate()`:

```php
use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;

$generator = app(AiGenerator::class);

$result = $generator->generate(new ContentRequest(topic: 'Your topic'));
$image = $generator->generateImage('A short description of the image, in English');
```

## Test your driver

Fake the HTTP call, so the test never reaches your provider. [Testing](testing.md) shows how.
