---
name: laravel-ai-generator-development
description: Work with darvis/laravel-ai-generator. Use it to generate articles, SEO fields and images with Claude, ChatGPT, Gemini or Grok, combine providers (text, images, fallbacks), set up API keys, use the MCP server, handle a failed text or image call, plug in another AI provider, and test code that generates content without calling a real API.
---

# darvis/laravel-ai-generator development

## When to use this skill

Use this skill when code generates content or images in an application that has `darvis/laravel-ai-generator` installed, when you choose or combine providers, when a generation fails or comes back without an image, when you add a driver for another AI provider, or when you write tests around generated content.

## Providers

| Driver | Aliases | API key | Text | Images |
| --- | --- | --- | --- | --- |
| `openai` | `chatgpt`, `gpt` | `OPENAI_API_KEY` | Responses API, strict JSON schema | yes |
| `anthropic` | `claude` | `ANTHROPIC_API_KEY` | Messages API, `output_config.format` JSON schema | no |
| `gemini` | `google` | `GEMINI_API_KEY` | `generateContent`, `responseJsonSchema` | yes |
| `xai` | `grok` | `XAI_API_KEY` | chat completions, strict JSON schema | yes |

- `AI_GENERATOR_DRIVER` picks the default. `AiGenerator::using('anthropic')` or `using('openai', 'model-id')` picks one for a single call.
- `AI_GENERATOR_IMAGE_DRIVER` picks the image provider. Empty: the text driver when it can make images, OpenAI when it cannot (Claude, custom drivers).
- `AI_GENERATOR_FALLBACKS=anthropic,openai`: when the default driver's text call throws, these are tried in order. A driver without key is skipped. `using()` never falls back. When all fail: `RuntimeException` `Every AI driver failed. openai: … | anthropic: …`.
- `$result->driver` and `$result->model` say who wrote the text (null for a custom driver).
- A temperature is only sent when configured, except OpenAI (0.7). The newest Claude models reject one; for an OpenAI reasoning model set `OPENAI_TEMPERATURE=` empty.
- Setting up keys: `php artisan ai-generator:install` checks every key against the provider (the model list, which costs nothing) and writes `.env`. `php artisan ai-generator:status` checks them again; `--test=claude` generates one short, billed text. A ChatGPT, Claude or Gemini subscription is not an API key.

## How a generation runs

1. `AiGenerator::generate(ContentRequest $request)` fills the empty `language`, `tone`, `readingLevel` and `maxWords` from the config. `imageStyle` falls back to `photo`, `imageAspect` to `16:9`.
2. The driver sends one text request with a JSON schema and reads `title`, `intro`, `text`, `seo_title`, `seo_description` and `image_prompt` from the answer.
3. When `includeImage` is true, which is the **default**, the image driver makes a second call with the image prompt the model wrote.
4. Every text field is trimmed and a `ContentResult` comes back.

`{Name}` below is `OpenAI`, `Claude`, `Gemini` or `Grok`; `{KEY}` is the API key variable of the driver.

| Step | Fails when | What you get |
| --- | --- | --- |
| API key | `{KEY}` is empty | `RuntimeException`: `{KEY} is not set.` |
| Text call | connection error or timeout, on both of its 2 attempts | `RuntimeException`: `{Name} connection failed: …` |
| Text call | HTTP 4xx or 5xx, on both of its 2 attempts | `RuntimeException`: `{Name} request failed: …` |
| Text call | the output is not JSON, is cut off, refused or blocked | `RuntimeException` naming the provider |
| Image call | HTTP error, timeout or missing key of the image driver | no exception: the text comes back, `hasError()` is true, `errorMessage` starts with `{Name} Image Error:` |
| Image call | the image driver cannot make images (Claude) | no exception: `errorMessage` says to set `AI_GENERATOR_IMAGE_DRIVER` |
| Resolving a driver | a name the package does not know | `RuntimeException`: `Unsupported AI driver: …` |

## Generating content

Only `topic` is required. Pass `includeImage: false` when the page has no image: it saves the second API call, which the image provider bills separately.

```php
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;

$result = AiGenerator::generate(new ContentRequest(
    topic: 'Sustainable packaging for web shops',
    language: 'en',
    audience: 'shop owners',
    tone: 'neutral',             // informal, neutral or formal
    readingLevel: 'general',     // simple, general or expert
    keywords: ['packaging', 'sustainability'],
    cta: 'Request a sample box',
    brand: 'Acme',
    maxWords: 600,
    includeImage: true,
    imageStyle: 'illustration',  // photo, illustration, flat or 3d
    imageAspect: '16:9',         // 1:1, 4:5 or 16:9
));
```

`ContentRequest` and `ContentResult` are immutable: build a new one instead of changing a property.

## Saving the result

- `text` is HTML written by a model. Sanitise it before you render it unescaped.
- An image arrives as `imageUrl` or as `imageBase64`, depending on the image model. Handle both, and store the file yourself: the package does not download or save anything.
- Check `hasError()` before you mark a result as complete. A result with an error still has all its text.

```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

if ($result->hasImage()) {
    $binary = $result->imageBase64 !== null
        ? base64_decode($result->imageBase64)
        : Http::get($result->imageUrl)->body();

    Storage::disk('public')->put("articles/{$post->id}.png", $binary);
}

if ($result->hasError()) {
    report(new RuntimeException($result->errorMessage));
}
```

## Only an image

`AiGenerator::generateImage($prompt, $style, $aspect, $driver = null)` returns an array and never throws: `url` and `base64` on success, `error` on failure. Without `$driver` it uses the image driver (see Providers), which is OpenAI for the `openai` and `anthropic` text drivers and for a custom driver. It uses its own timeout of 120 seconds and a single attempt, not the driver timeout.

```php
$image = AiGenerator::generateImage('A cardboard box on a wooden table', 'photo', '1:1');

if (isset($image['error'])) {
    // log it and carry on without an image
}
```

With OpenAI the aspect ratio only changes the size with a `dall-e-3` image model; other OpenAI models get a square 1024x1024 image. Gemini follows the ratio, and Grok too, with `3:4` for `4:5`.

## Queue it

Every request inside `generate()` waits up to the driver's timeout (`OPENAI_TIMEOUT`, `ANTHROPIC_TIMEOUT`, `GEMINI_TIMEOUT` or `XAI_TIMEOUT`, 45 seconds by default) and is tried twice, the text and the image alike: four waits in the worst case. Generate in a queued job, not in a web request, give the job a `$timeout` above that worst case and `$tries = 1` so a failure is not billed again by itself. Raise the timeout of the image driver when images time out while the text succeeds. Fallback drivers add their own waits.

## Another AI provider

Claude, Gemini and Grok are built in. For any other provider, implement `Darvis\LaravelAiGenerator\Contracts\AiContentDriver` and register it with the manager in the `boot()` method of your own service provider:

```php
use App\Services\AiDrivers\AcmeAiDriver;
use Darvis\LaravelAiGenerator\AiGeneratorManager;

$this->app->make(AiGeneratorManager::class)
    ->extend('acme', fn ($app, ?string $model) => new AcmeAiDriver($model));
```

- Then use it with `AI_GENERATOR_DRIVER=acme`, `AiGenerator::using('acme')` or in `AI_GENERATOR_FALLBACKS`.
- Register it before anything resolves `AiGenerator`, which is a singleton that keeps its default driver.
- Replacing the binding, `$this->app->singleton(AiContentDriver::class, fn () => new AcmeAiDriver)`, still works. Don't use `$this->app->extend()` on `AiContentDriver`: the package binding throws `Unsupported AI driver` on an unknown name before your extender runs.
- The package makes no image for a custom driver's text unless the driver does it itself; `generateImage()` uses the image driver (OpenAI by default).
- The driver receives a request with the defaults already filled in, and returns a `ContentResult`. Throw a `RuntimeException` when the text fails; put an image failure in `errorMessage` instead, so the text is not lost.
- Keep your driver's own settings in your application's config, for example `config/services.php`.

## MCP server

With `laravel/mcp` (Laravel 12.41 or newer) installed, the package registers a local MCP server, `ai-generator`, with the tools `list-providers`, `generate-content` (`include_image` defaults to false) and `generate-image`. Start it with `php artisan mcp:start ai-generator`; add it to Claude Code with `claude mcp add ai-generator -- php artisan mcp:start ai-generator`. `AI_GENERATOR_MCP=false` turns it off. The package registers no web server: expose `Darvis\LaravelAiGenerator\Mcp\AiGeneratorServer` with `Mcp::web()` only behind authentication, because every call spends the application's credits.

## Reading settings

Read the package settings through `Darvis\LaravelAiGenerator\Support\AiGeneratorConfig`: `driver()`, `imageDriver()`, `fallbacks()`, `defaultLanguage()`, `defaultTone()`, `defaultReadingLevel()`, `defaultMaxWords()`, and per provider (`Darvis\LaravelAiGenerator\Support\Provider::Anthropic` and so on) `apiKey()` (null when empty), `baseUrl()`, `model()`, `imageModel()`, `temperature()` and `timeout()`. The `openAi…()` accessors still work. The defaults are written there once.

`OPENAI_BASE_URL` points the driver at another host, such as a proxy. The package appends `/responses` and `/images/generations` and sends the key as a Bearer token, so the endpoint has to accept exactly that.

## Testing

Never call a real AI API from a test. Two ways:

Bind a fake driver when the test is about your own code. Bind it before anything resolves `AiGenerator`: the generator is a singleton and keeps the driver it was built with.

```php
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;

$this->app->singleton(AiContentDriver::class, fn () => new class implements AiContentDriver
{
    public function generate(ContentRequest $request): ContentResult
    {
        return new ContentResult(
            title: 'Title',
            intro: 'Intro',
            text: '<p>Text</p>',
            seoTitle: 'SEO title',
            seoDescription: 'SEO description',
        );
    }
});
```

Use `Http::fake()` when a built-in driver itself has to run. For OpenAI the text comes back in the shape of the Responses API, and the API key has to be set or the driver throws before it sends anything:

```php
use Illuminate\Support\Facades\Http;

config()->set('ai-generator.drivers.openai.api_key', 'test-key');

Http::fake([
    '*/responses' => Http::response(['output' => [['content' => [[
        'type' => 'output_text',
        'text' => json_encode([
            'title' => 'Title',
            'intro' => 'Intro',
            'text' => '<p>Text</p>',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'image_prompt' => 'A box',
        ]),
    ]]]]]),
    '*/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('png')]]]),
]);
```

Add `Http::preventStrayRequests()` so a forgotten fake fails the test instead of spending money.

For Claude fake `api.anthropic.com/v1/messages` with `['content' => [['type' => 'text', 'text' => json_encode([...])]]]`; for Gemini `generativelanguage.googleapis.com/*` with `['candidates' => [['content' => ['parts' => [['text' => json_encode([...])]]]]]]`; for Grok `api.x.ai/v1/chat/completions` with `['choices' => [['message' => ['content' => json_encode([...])]]]]`. Set the provider's API key in the test config first.
