---
title: "API reference"
nav_order: 11
description: "Every public class and method: AiGenerator, the driver manager, ContentRequest, ContentResult, the driver contracts, the facade, config accessors, commands and exceptions."
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
2. Calls `generate()` on the bound `AiContentDriver`, the default driver.
3. When that throws a `RuntimeException` and `AI_GENERATOR_FALLBACKS` lists drivers, tries them in
   order, skipping a built-in driver without an API key. When all fail, throws
   `Every AI driver failed. ...` with the first exception as `getPrevious()`.
4. Trims `title`, `intro`, `text`, `seoTitle`, `seoDescription` and `imagePrompt`.

Without fallbacks it throws whatever the driver throws. The built-in drivers throw a
`RuntimeException`; see [Exceptions](#exceptions).

```php
use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;

$result = app(AiGenerator::class)->generate(new ContentRequest(
    topic: 'Your topic',
    includeImage: false,
));
```

### using()

```php
public function using(string $driver, ?string $model = null): AiGenerator
```

Returns a new generator that writes with another driver, and with `$model` instead of the configured
text model when you pass one. `$driver` is `openai`, `anthropic`, `gemini`, `xai`, an alias
(`chatgpt`, `gpt`, `claude`, `google`, `grok`) or a name registered with
[`extend()`](#aigeneratormanager). The returned generator never uses fallbacks. Throws
`Unsupported AI driver: ...` for an unknown name.

```php
AiGenerator::using('claude')->generate($request);
AiGenerator::using('openai', 'gpt-6-luna')->generate($request);
```

### generateImage()

```php
public function generateImage(string $prompt, string $style = 'photo', string $aspect = '16:9', ?string $driver = null): array
```

Makes only an image. The image comes from `$driver` when you pass one (`openai`, `gemini` or `xai`),
or else from `AI_GENERATOR_IMAGE_DRIVER`, or else from the bound text driver when it can make images,
or else from OpenAI. With the `openai` or `anthropic` driver, or a custom driver, that is the OpenAI
image API with `OPENAI_IMAGE_MODEL`.

| Argument | Values |
| --- | --- |
| `$prompt` | What the image shows, preferably in English. |
| `$style` | `photo`, `illustration`, `flat` or `3d`. The style becomes a prefix of the prompt and `No text or watermarks.` is added. Any other value sends the prompt unchanged. |
| `$aspect` | `1:1`, `4:5` or `16:9`. Gemini gets the ratio; Grok gets it too, with `3:4` for `4:5`. With OpenAI only a model name that contains `dall-e-3` gets a matching size; other models get `1024x1024`. |
| `$driver` | `openai`, `gemini`, `xai` or an alias, or `null` for the rules above. |

It returns an array and does not throw:

- on success `['url' => ?string, 'base64' => ?string]`, one of the two filled;
- on failure `['error' => string]`, for example `OPENAI_API_KEY is not set.`, or
  `Claude (Anthropic) cannot generate images. Use openai, gemini or xai as image driver.`, or
  `Unsupported AI driver: ...`.

The request waits up to 120 seconds, with 30 seconds to connect, and is tried once. The timeout of
the provider does not apply here.

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
    ?string $driver = null,
    ?string $model = null,
)
```

`driver` and `model` say which built-in driver and text model wrote the text, for example
`anthropic` and `claude-sonnet-5`. A custom driver leaves them `null`.

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

The package binds it as a singleton to the driver the manager builds for the config key `driver`:
`OpenAiDriver`, `AnthropicDriver`, `GeminiDriver` or `XaiDriver` in `Darvis\LaravelAiGenerator\Drivers`,
or a driver registered with `extend()`. It throws `Unsupported AI driver: ...` for any other name. See
[Custom drivers](custom-drivers.md) for a driver of your own.

The built-in drivers extend `Darvis\LaravelAiGenerator\Drivers\Driver`, which is `@internal`: extend
`AiContentDriver` for a driver of your own, not that class. `OpenAiDriver::imagePayload()` is public
but `@internal` too. Don't call either from your application.

## AiImageDriver {#aiimagedriver}

`Darvis\LaravelAiGenerator\Contracts\AiImageDriver`, the interface of a driver that can make images.
The OpenAI, Gemini and Grok drivers implement it; Claude does not.

```php
public function generateImage(string $prompt, string $aspect = '16:9', ?int $timeout = null, int $attempts = 2): array;
```

`$prompt` is the complete prompt; the style prefix is added before this call. `$timeout` is in seconds
per attempt, `null` for the timeout of the provider. It returns the same array as
`AiGenerator::generateImage()` and never throws. A custom driver may implement it too; the manager then
uses it for images when it is the text driver or the configured image driver.

## AiGeneratorManager {#aigeneratormanager}

`Darvis\LaravelAiGenerator\AiGeneratorManager`, a singleton that builds the drivers by name.

| Method | What it does |
| --- | --- |
| `driver(?string $name = null): AiContentDriver` | The driver with this name, or the default driver. The instance is reused. |
| `build(string $name, ?string $model = null): AiContentDriver` | A new instance, optionally with another text model. `using()` calls this when you pass a model. |
| `extend(string $name, Closure $creator): self` | Registers a driver of your own. The closure gets the container and the model asked for with `using()`, or `null`. |
| `imageDriver(?string $name = null): ?AiImageDriver` | The driver with this name when it can make images, else `null`. Without a name: the image driver of the default driver. |
| `imageDriverFor(AiContentDriver $textDriver): ?AiImageDriver` | The image driver for a text driver: `AI_GENERATOR_IMAGE_DRIVER`, else the text driver when it can make images, else OpenAI. |
| `names(): list<string>` | The built-in driver names and the names registered with `extend()`. |
| `normalize(string $name): string` | Turns an alias such as `claude` into the driver name, `anthropic`. |

All of them except `names()` and `normalize()` throw `Unsupported AI driver: ...` for an unknown name.

```php
use App\Services\AiDrivers\AcmeAiDriver;
use Darvis\LaravelAiGenerator\AiGeneratorManager;

app(AiGeneratorManager::class)->extend('acme', fn ($app, ?string $model) => new AcmeAiDriver);
```

## Provider {#provider}

`Darvis\LaravelAiGenerator\Support\Provider`, an enum of the providers the package ships a driver for:
`Provider::OpenAi` (`openai`), `Provider::Anthropic` (`anthropic`), `Provider::Gemini` (`gemini`) and
`Provider::Xai` (`xai`).

| Method | Returns, for example for `Provider::Anthropic` |
| --- | --- |
| `Provider::fromName(string $name): ?Provider` | The case for a name or alias, `null` for an unknown name. |
| `label(): string` | `Claude (Anthropic)` |
| `shortName(): string` | `Claude`, as used in error messages. |
| `envPrefix(): string` | `ANTHROPIC` |
| `apiKeyEnv(): string` | `ANTHROPIC_API_KEY` |
| `apiKeyUrl(): string` | The page where you create a key. |
| `defaultBaseUrl(): string` | `https://api.anthropic.com/v1` |
| `defaultModel(): string` | `claude-sonnet-5` |
| `defaultImageModel(): ?string` | `null`: Claude makes no images. |
| `defaultTemperature(): ?float` | `null`; `0.7` for OpenAI. |
| `supportsImages(): bool` | `false` |

## The facade

`Darvis\LaravelAiGenerator\Facades\AiGenerator` forwards to the `AiGenerator` singleton. It has no
global alias, so import it.

```php
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;

AiGenerator::generate(new ContentRequest(topic: 'Your topic', includeImage: false));
AiGenerator::generateImage('A lighthouse at dusk', 'illustration', '1:1');
AiGenerator::using('anthropic')->generate(new ContentRequest(topic: 'Your topic', includeImage: false));
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
| `apiKey(Provider $provider)` | `?string` | `drivers.<driver>.api_key` | `null`, also for an empty string |
| `baseUrl(Provider $provider)` | `string` | `drivers.<driver>.base_url` | per provider, without a trailing slash |
| `model(Provider $provider)` | `string` | `drivers.<driver>.model` | per provider, also for an empty string |
| `imageModel(Provider $provider)` | `?string` | `drivers.<driver>.image_model` | per provider; `null` for Claude |
| `temperature(Provider $provider)` | `?float` | `drivers.<driver>.temperature` | `0.7` for OpenAI, else `null`; an empty string gives `null` |
| `timeout(Provider $provider)` | `int` | `drivers.<driver>.timeout` | `45` |
| `anthropicMaxTokens()` | `int` | `drivers.anthropic.max_tokens` | `8192` |
| `anthropicVersion()` | `string` | `drivers.anthropic.version` | `2023-06-01` |
| `imageDriver()` | `?string` | `image_driver` | `null` |
| `fallbacks()` | `list<string>` | `fallbacks` | `[]`; a comma separated string is split and trimmed |
| `mcpEnabled()` | `bool` | `mcp.enabled` | `true` |
| `mcpHandle()` | `string` | `mcp.handle` | `ai-generator` |

The `openAi...()` methods are kept for existing code; they return the same as the general ones with
`Provider::OpenAi`.

[Configuration](configuration.md) has the environment variable of every key.

## The service provider

`Darvis\LaravelAiGenerator\AiGeneratorServiceProvider` is discovered by Laravel. It merges the config
under the key `ai-generator`, registers the manager, the driver and the generator as singletons and
the alias, registers the two commands, and offers one publish tag:

```bash
php artisan vendor:publish --tag=ai-generator-config
```

When `laravel/mcp` is installed and `AI_GENERATOR_MCP` is not `false`, it registers the local
[MCP server](mcp.md) `Darvis\LaravelAiGenerator\Mcp\AiGeneratorServer` under `AI_GENERATOR_MCP_HANDLE`.

The package has no routes, views, migrations, events or translations.

## Commands

| Command | What it does | Billed |
| --- | --- | --- |
| `php artisan ai-generator:install` | The setup wizard: providers, keys (checked), models, default, image driver, fallbacks, defaults; writes `.env`. | no |
| `php artisan ai-generator:status` | A table of the providers, their keys, models and a key check, plus the default driver, image driver, fallbacks and MCP. Fails when a key is refused. | no |
| `php artisan ai-generator:status --offline` | The same without contacting any provider. | no |
| `php artisan ai-generator:status --test=anthropic` | Also generates one short text with that driver, without an image. | yes, one call |

## Exceptions

The package has no exception classes of its own. The drivers, the manager and the generator throw
PHP's `RuntimeException` with these messages. `Provider` stands for `OpenAI`, `Claude`, `Gemini` or
`Grok`.

| Message | When |
| --- | --- |
| `OPENAI_API_KEY is not set.` (or `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`, `XAI_API_KEY`) | The API key of the driver is empty. Nothing was sent. |
| `Provider connection failed: ...` | No connection or a timeout on the text request, on both attempts. |
| `Provider request failed: ...` | A 4xx or 5xx status on the text request, on both attempts. |
| `Provider returned non-JSON output (unexpected).` | The text of the answer is not a JSON object. |
| `OpenAI response did not include output_text.` | The OpenAI answer has no `output_text` item. |
| `Claude response did not include a text block.` | The Claude answer has no `text` block. |
| `Claude stopped before the answer was complete. Raise ANTHROPIC_MAX_TOKENS or lower maxWords.` | Claude reached `ANTHROPIC_MAX_TOKENS`. |
| `Claude declined to write about this topic.` | Claude refused. |
| `Gemini blocked the prompt: ...` | Google's safety filter blocked the prompt. |
| `Gemini response did not include text (finish reason: ...).` | The Gemini answer has no text. |
| `Grok declined to write about this topic: ...` | Grok refused. |
| `Grok response did not include message content.` | The Grok answer has no message content. |
| `Every AI driver failed. ...` | The default driver and every fallback driver failed. |
| `Unsupported AI driver: ...` | No built-in or registered driver has this name. |

The previous exception, from Laravel's HTTP client, is available through `getPrevious()` for the
connection and request failures.

A failing image inside `generate()` does not throw. The result has `errorMessage` set to
`OpenAI Image Error: ...`, `Gemini Image Error: ...` or `Grok Image Error: ...`, or to
`Provider cannot generate images. Set AI_GENERATOR_IMAGE_DRIVER to openai, gemini or xai.` when the
image driver cannot make images. [Troubleshooting](troubleshooting.md) has the cause and the fix of every
message.
