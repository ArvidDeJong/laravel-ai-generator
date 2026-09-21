---
name: laravel-ai-generator-development
description: Work with darvis/laravel-ai-generator. Use it to generate articles, SEO fields and images with OpenAI, handle a failed text or image call, plug in another AI provider, and test code that generates content without calling a real API.
---

# darvis/laravel-ai-generator development

## When to use this skill

Use this skill when code generates content or images in an application that has `darvis/laravel-ai-generator` installed, when a generation fails or comes back without an image, when you add a driver for another AI provider, or when you write tests around generated content.

## How a generation runs

1. `AiGenerator::generate(ContentRequest $request)` fills the empty `language`, `tone`, `readingLevel` and `maxWords` from the config. `imageStyle` falls back to `photo`, `imageAspect` to `16:9`.
2. The bound `AiContentDriver` is called. The OpenAI driver posts to the Responses API (`/responses`) with a strict JSON schema and reads `title`, `intro`, `text`, `seo_title`, `seo_description` and `image_prompt` from the answer.
3. When `includeImage` is true, which is the **default**, the driver makes a second call to `/images/generations` with the image prompt the model wrote.
4. Every text field is trimmed and a `ContentResult` comes back.

| Step | Fails when | What you get |
| --- | --- | --- |
| API key | `OPENAI_API_KEY` is empty | `RuntimeException`: `OPENAI_API_KEY is not set.` |
| Text call | connection error or timeout, on both of its 2 attempts | `RuntimeException`: `OpenAI connection failed: …` |
| Text call | HTTP 4xx or 5xx, on both of its 2 attempts | `RuntimeException`: `OpenAI request failed: …` |
| Text call | the output is not JSON, or has no `output_text` | `RuntimeException` |
| Image call | HTTP error or timeout, on both of its 2 attempts | no exception: the text comes back, `hasError()` is true, `errorMessage` starts with `OpenAI Image Error:` |
| Service provider | `AI_GENERATOR_DRIVER` is a name the package does not know | `RuntimeException`: `Unsupported AI driver: …`, as soon as the driver is resolved |

## Generating content

Only `topic` is required. Pass `includeImage: false` when the page has no image: it saves the second API call, which OpenAI bills separately.

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

`AiGenerator::generateImage($prompt, $style, $aspect)` returns an array and never throws: `url` and `base64` on success, `error` on failure. It always calls the OpenAI image API, also when another text driver is bound, and it uses its own timeout of 120 seconds and a single attempt, not `OPENAI_TIMEOUT`.

```php
$image = AiGenerator::generateImage('A cardboard box on a wooden table', 'photo', '1:1');

if (isset($image['error'])) {
    // log it and carry on without an image
}
```

The aspect ratio only changes the size with a `dall-e-3` image model. Other models get a square 1024x1024 image.

## Queue it

Every request inside `generate()` waits up to `OPENAI_TIMEOUT` seconds (45 by default) and is tried twice, the text and the image alike: four waits in the worst case. Generate in a queued job, not in a web request, give the job a `$timeout` above that worst case and `$tries = 1` so a failure is not billed again by itself. Raise `OPENAI_TIMEOUT` when images time out while the text succeeds.

## Another AI provider

Implement `Darvis\LaravelAiGenerator\Contracts\AiContentDriver` and **replace the binding** in the `register()` method of your own service provider:

```php
use App\Services\AiDrivers\AcmeAiDriver;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;

$this->app->singleton(AiContentDriver::class, fn () => new AcmeAiDriver);
```

- Don't use `$this->app->extend()`: it builds the package binding first, and that throws `Unsupported AI driver` on a name it does not know before the extender runs.
- `AI_GENERATOR_DRIVER` may hold your own name, for example to bind your driver only `if (AiGeneratorConfig::driver() === 'acme')`. That name only works while your binding replaces the package's; without it the same exception is thrown.
- The package makes no image for a custom driver: `includeImage` does nothing unless the driver acts on it.
- The driver receives a request with the defaults already filled in, and returns a `ContentResult`. Throw a `RuntimeException` when the text fails; put an image failure in `errorMessage` instead, so the text is not lost.
- Keep your driver's own settings in your application's config, for example `config/services.php`.

## Reading settings

Read the package settings through `Darvis\LaravelAiGenerator\Support\AiGeneratorConfig`: `driver()`, `defaultLanguage()`, `defaultTone()`, `defaultReadingLevel()`, `defaultMaxWords()`, `openAiApiKey()` (null when empty), `openAiBaseUrl()`, `openAiModel()`, `openAiImageModel()`, `openAiTemperature()` and `openAiTimeout()`. The defaults are written there once.

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

Use `Http::fake()` when the OpenAI driver itself has to run. The text comes back in the shape of the Responses API, and the API key has to be set or the driver throws before it sends anything:

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
