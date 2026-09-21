---
title: "API reference"
nav_order: 8
description: "Every public class and method: AiGenerator, ContentRequest, ContentResult, the AiContentDriver contract, the facade, the config accessors and exception messages."
---

# API reference

Everything lives in the namespace `Darvis\LaravelAiGenerator`.

## AiGenerator

`Darvis\LaravelAiGenerator\AiGenerator`, a `final` class, bound in the container as a singleton and
under the alias `ai-generator`.

### generate()

```php
public function generate(ContentRequest $request): ContentResult
```

1. Fills the empty `language`, `tone`, `readingLevel` and `maxWords` of the request from the config.
   An empty `imageStyle` becomes `photo`, an empty `imageAspect` becomes `16:9`.
2. Calls `generate()` on the bound `AiContentDriver`.
3. Trims `title`, `intro`, `text`, `seoTitle`, `seoDescription` and `imagePrompt`.

It throws whatever the driver throws. The OpenAI driver throws a `RuntimeException`; see
[Exceptions](#exceptions).

```php
use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;

$result = app(AiGenerator::class)->generate(new ContentRequest(
    topic: 'Your topic',
    includeImage: false,
));
```

### generateImage()

```php
public function generateImage(string $prompt, string $style = 'photo', string $aspect = '16:9'): array
```

Makes only an image. It always calls the OpenAI image API with `OPENAI_IMAGE_MODEL`, whatever driver
is bound for the text.

| Argument | Values |
| --- | --- |
| `$prompt` | What the image shows, preferably in English. |
| `$style` | `photo`, `illustration`, `flat` or `3d`. The style becomes a prefix of the prompt and `No text or watermarks.` is added. Any other value sends the prompt unchanged. |
| `$aspect` | `1:1`, `4:5` or `16:9`. Only a model name that contains `dall-e-3` gets a matching size; other models get `1024x1024`. |

It returns an array and does not throw:

- on success `['url' => ?string, 'base64' => ?string]`, one of the two filled;
- on failure `['error' => string]`, for example `OPENAI_API_KEY is not set.`

The request waits up to 120 seconds, with 30 seconds to connect, and is tried once. `OPENAI_TIMEOUT`
does not apply here.

```php
use Darvis\LaravelAiGenerator\AiGenerator;
use Illuminate\Support\Facades\Log;

$image = app(AiGenerator::class)->generateImage('A lighthouse at dusk', 'illustration');

if (isset($image['error'])) {
    Log::warning($image['error']);
}
```

## ContentRequest

`Darvis\LaravelAiGenerator\ContentRequest`, a `final` class with `public readonly` properties.

```php
public function __construct(
    string $topic,
    ?string $language = null,
    ?string $audience = null,
    ?string $tone = null,
    ?string $readingLevel = null,
    ?array $keywords = null,
    ?string $cta = null,
    ?string $brand = null,
    ?int $maxWords = null,
    bool $includeImage = true,
    ?string $imageStyle = 'photo',
    ?string $imageAspect = '16:9',
)
```

Use named arguments. [Usage](usage.md#every-request-option) explains every argument. Note that
`includeImage` defaults to `true`.

## ContentResult

`Darvis\LaravelAiGenerator\ContentResult`, a `final` class with `public readonly` properties.

```php
public function __construct(
    string $title,
    string $intro,
    string $text,
    string $seoTitle,
    string $seoDescription,
    ?string $imagePrompt = null,
    ?string $imageUrl = null,
    ?string $imageBase64 = null,
    ?string $errorMessage = null,
)
```

| Method | Returns |
| --- | --- |
| `hasError(): bool` | `true` when `errorMessage` is not `null`. The text can still be complete. |
| `hasImage(): bool` | `true` when `imageUrl` or `imageBase64` is not `null`. |

[Usage](usage.md#what-comes-back) explains every property.

## AiContentDriver

`Darvis\LaravelAiGenerator\Contracts\AiContentDriver`, the interface of a driver.

```php
public function generate(ContentRequest $request): ContentResult;
```

The package binds it as a singleton to `Darvis\LaravelAiGenerator\Drivers\OpenAiDriver` when the
config key `driver` is `openai`, and throws `Unsupported AI driver: ...` for any other name. See
[Custom drivers](custom-drivers.md) for a driver of your own.

`OpenAiDriver::imagePayload()` is public but marked `@internal`. Don't call it from your application.

## The facade

`Darvis\LaravelAiGenerator\Facades\AiGenerator` forwards to the `AiGenerator` singleton. It has no
global alias, so import it.

```php
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;

AiGenerator::generate(new ContentRequest(topic: 'Your topic', includeImage: false));
AiGenerator::generateImage('A lighthouse at dusk', 'illustration', '1:1');
```

## AiGeneratorConfig {#aigeneratorconfig}

`Darvis\LaravelAiGenerator\Support\AiGeneratorConfig` is the one class that reads the package config.
Every method is static.

| Method | Returns | Config key | Default |
| --- | --- | --- | --- |
| `driver()` | `string` | `driver` | `openai` |
| `defaultLanguage()` | `string` | `default_language` | `nl` |
| `defaultMaxWords()` | `int` | `defaults.max_words` | `900` |
| `defaultReadingLevel()` | `string` | `defaults.reading_level` | `general` |
| `defaultTone()` | `string` | `defaults.tone` | `informal` |
| `openAiApiKey()` | `?string` | `drivers.openai.api_key` | `null`, also for an empty string |
| `openAiBaseUrl()` | `string` | `drivers.openai.base_url` | `https://api.openai.com/v1`, without a trailing slash |
| `openAiImageModel()` | `string` | `drivers.openai.image_model` | `gpt-image-1` |
| `openAiModel()` | `string` | `drivers.openai.model` | `gpt-4.1-mini` |
| `openAiTemperature()` | `float` | `drivers.openai.temperature` | `0.7` |
| `openAiTimeout()` | `int` | `drivers.openai.timeout` | `45` |

[Configuration](configuration.md) has the environment variable of every key.

## The service provider

`Darvis\LaravelAiGenerator\AiGeneratorServiceProvider` is discovered by Laravel. It merges the config
under the key `ai-generator`, registers the two singletons and the alias, and offers one publish tag:

```bash
php artisan vendor:publish --tag=ai-generator-config
```

The package has no routes, views, migrations, commands, events or translations.

## Exceptions

The package has no exception classes of its own. The OpenAI driver and the service provider throw
PHP's `RuntimeException` with these messages:

| Message | When |
| --- | --- |
| `OPENAI_API_KEY is not set.` | The API key is empty. Nothing was sent. |
| `OpenAI connection failed: ...` | No connection or a timeout on the text request, on both attempts. |
| `OpenAI request failed: ...` | A 4xx or 5xx status on the text request, on both attempts. |
| `OpenAI returned non-JSON output (unexpected).` | The text of the answer is not a JSON object. |
| `OpenAI response did not include output_text.` | The answer has no `output_text` item. |
| `Unsupported AI driver: ...` | The config key `driver` is not `openai` and the binding was not replaced. |

The previous exception, from Laravel's HTTP client, is available through `getPrevious()` for the
connection and request failures.

A failing image inside `generate()` does not throw. The result has `errorMessage` set to
`OpenAI Image Error: ...`. [Troubleshooting](troubleshooting.md) has the cause and the fix of every
message.
