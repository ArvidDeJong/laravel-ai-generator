---
title: Custom drivers
nav_order: 5
description: "Plugging in another AI provider such as Anthropic or a local Ollama model by implementing AiContentDriver and binding it."
---

# Custom Drivers

The package uses a driver-based architecture, making it easy to add support for other AI providers like Anthropic Claude, Google Gemini, or local models.

## Creating a Custom Driver

### Step 1: Implement the Interface

Create a new class that implements `AiContentDriver`:

```php
<?php

namespace App\Services\AiDrivers;

use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;
use Illuminate\Support\Facades\Http;

class AnthropicDriver implements AiContentDriver
{
    public function generate(ContentRequest $request): ContentResult
    {
        $response = Http::withHeaders([
            'x-api-key' => config('ai-generator.drivers.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => config('ai-generator.drivers.anthropic.model', 'claude-3-sonnet'),
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'user', 'content' => $this->buildPrompt($request)],
            ],
        ]);

        $data = $response->json();
        $content = $this->parseResponse($data);

        return new ContentResult(
            title: $content['title'],
            intro: $content['intro'],
            text: $content['text'],
            seoTitle: $content['seo_title'],
            seoDescription: $content['seo_description'],
            imagePrompt: $content['image_prompt'] ?? null,
        );
    }

    private function buildPrompt(ContentRequest $request): string
    {
        // Build your prompt here
        return "Generate content about: {$request->topic}...";
    }

    private function parseResponse(array $data): array
    {
        // Parse the API response
        $text = $data['content'][0]['text'] ?? '';
        return json_decode($text, true);
    }
}
```

### Step 2: Add Configuration

Add your driver configuration to `config/ai-generator.php`:

```php
'drivers' => [
    'openai' => [
        // existing config...
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-3-sonnet-20240229'),
    ],
],
```

### Step 3: Register the Driver

Bind your driver in a service provider of your own. The package binds `AiContentDriver` for the
drivers it knows and throws on any other name, so replace that binding instead of extending it:
`extend()` would first build the package binding and never reach your code.

```php
<?php

namespace App\Providers;

use App\Services\AiDrivers\AnthropicDriver;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Illuminate\Support\ServiceProvider;

class AiGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('ai-generator.driver') === 'anthropic') {
            $this->app->singleton(AiContentDriver::class, fn () => new AnthropicDriver());
        }
    }
}
```

Your provider runs after the package provider, so its binding wins. With any other driver name
the package binding stays in place.

Register it in `config/app.php` or `bootstrap/providers.php`:

```php
// bootstrap/providers.php (Laravel 11+)
return [
    // ...
    App\Providers\AiGeneratorServiceProvider::class,
];
```

### Step 4: Use Your Driver

Set the driver in your `.env`:

```env
AI_GENERATOR_DRIVER=anthropic
ANTHROPIC_API_KEY=sk-ant-your-key-here
```

## Example: Local LLM Driver

Here's an example for a local Ollama instance:

```php
<?php

namespace App\Services\AiDrivers;

use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;
use Illuminate\Support\Facades\Http;

class OllamaDriver implements AiContentDriver
{
    public function generate(ContentRequest $request): ContentResult
    {
        $response = Http::timeout(120)
            ->post(config('ai-generator.drivers.ollama.base_url') . '/api/generate', [
                'model' => config('ai-generator.drivers.ollama.model', 'llama2'),
                'prompt' => $this->buildPrompt($request),
                'format' => 'json',
                'stream' => false,
            ]);

        $content = json_decode($response->json('response'), true);

        return new ContentResult(
            title: $content['title'] ?? '',
            intro: $content['intro'] ?? '',
            text: $content['text'] ?? '',
            seoTitle: $content['seo_title'] ?? '',
            seoDescription: $content['seo_description'] ?? '',
        );
    }

    private function buildPrompt(ContentRequest $request): string
    {
        return <<<PROMPT
Generate a JSON object with the following structure for the topic "{$request->topic}":
{
    "title": "article title",
    "intro": "2-4 sentence introduction",
    "text": "main content with HTML formatting",
    "seo_title": "SEO title max 60 chars",
    "seo_description": "meta description max 155 chars"
}
PROMPT;
    }
}
```

Configuration:

```php
// config/ai-generator.php
'drivers' => [
    // ...
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'llama2'),
    ],
],
```

## Driver Best Practices

1. **Handle errors gracefully** - Catch API exceptions and throw `RuntimeException` with clear messages

2. **Respect timeouts** - Configure appropriate timeouts for your AI provider

3. **Implement retries** - Add retry logic for transient failures

4. **Validate responses** - Ensure the AI response matches the expected structure

5. **Support all request parameters** - Map `ContentRequest` fields to your provider's capabilities

6. **Return consistent results** - Always return a valid `ContentResult` object
