## darvis/laravel-ai-generator

Generates structured content with Claude, ChatGPT, Gemini or Grok: a title, a plain text intro, the main text as HTML, an SEO title, a meta description and optionally an image, from one `ContentRequest`.

- Call `Darvis\LaravelAiGenerator\Facades\AiGenerator::generate(new ContentRequest(topic: '...'))`, or inject `Darvis\LaravelAiGenerator\AiGenerator`. It returns a `ContentResult` with `title`, `intro`, `text`, `seoTitle`, `seoDescription`, `imagePrompt`, `imageUrl`, `imageBase64`, `errorMessage`, `driver` and `model`. Don't build calls to the provider APIs yourself.
- Drivers: `openai`, `anthropic`, `gemini`, `xai` (aliases `chatgpt`, `claude`, `google`, `grok`). `AI_GENERATOR_DRIVER` is the default. For one call: `AiGenerator::using('anthropic')` or `AiGenerator::using('openai', 'model-id')`. `using()` never falls back to another provider.
- Claude cannot make images. The image comes from `AI_GENERATOR_IMAGE_DRIVER`, or else from the text driver when it can, or else from OpenAI. `generateImage($prompt, $style, $aspect, $driver = null)` follows the same rule, never throws and returns `url`, `base64` or `error`.
- `AI_GENERATOR_FALLBACKS` (comma separated) lists drivers tried in order when the default driver's text call throws; a driver without API key is skipped.
- API keys live in `.env` only: `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`, `OPENAI_API_KEY`, `XAI_API_KEY`. Never hard code a key, log it or send it to the front end. `php artisan ai-generator:install` sets them up; `php artisan ai-generator:status` checks them without cost.
- `ContentRequest` uses named arguments. Only `topic` is required; `language`, `tone`, `readingLevel` and `maxWords` fall back to the config. `includeImage` is **true by default**, which costs a second API call: pass `includeImage: false` when the page has no image.
- A failing text call throws `RuntimeException`. A failing image does not: the text comes back with `hasError()` true. Check `hasError()` before saving a result as complete.
- `text` is HTML written by a model. Sanitise it before rendering it unescaped.
- Settings live under the config key `ai-generator`. Read them through `Darvis\LaravelAiGenerator\Support\AiGeneratorConfig` (`driver()`, `model(Provider::Anthropic)`, `apiKey(...)`, `imageDriver()`, `fallbacks()`), not with `config()`.
- Another provider: register it with `app(Darvis\LaravelAiGenerator\AiGeneratorManager::class)->extend('name', fn ($app, ?string $model) => new YourDriver)` in a service provider, where `YourDriver` implements `Darvis\LaravelAiGenerator\Contracts\AiContentDriver`.
- With `laravel/mcp` installed the package registers the local MCP server `ai-generator` (`php artisan mcp:start ai-generator`). Only expose it on the web behind authentication: every tool call is billed.
- Every request inside `generate()` waits up to the driver's timeout (45 seconds by default) and is tried twice. Generate in a queued job, not in a web request.
- In tests, never call a real API: use `Http::fake()` with `Http::preventStrayRequests()`, or bind a fake `AiContentDriver`.

@verbatim
<code-snippet name="Generate an article in a queued job" lang="php">
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;

$result = AiGenerator::using('claude')->generate(new ContentRequest(
    topic: $this->topic,
    language: 'en',
    keywords: ['laravel', 'hosting'],
    includeImage: false,
));

$this->post->update([
    'title' => $result->title,
    'intro' => $result->intro,
    'body' => $result->text,
    'meta_title' => $result->seoTitle,
    'meta_description' => $result->seoDescription,
]);
</code-snippet>
@endverbatim
