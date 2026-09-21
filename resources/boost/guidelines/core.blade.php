## darvis/laravel-ai-generator

Generates structured content with OpenAI: a title, a plain text intro, the main text as HTML, an SEO title, a meta description and optionally an image, from one `ContentRequest`.

- Call `Darvis\LaravelAiGenerator\Facades\AiGenerator::generate(new ContentRequest(topic: '...'))`, or inject `Darvis\LaravelAiGenerator\AiGenerator`. It returns a `ContentResult` with `title`, `intro`, `text`, `seoTitle`, `seoDescription`, `imagePrompt`, `imageUrl`, `imageBase64` and `errorMessage`. Don't build calls to the OpenAI API yourself.
- `ContentRequest` uses named arguments. Only `topic` is required; `language`, `tone`, `readingLevel` and `maxWords` fall back to the config. `includeImage` is **true by default**, which costs a second API call: pass `includeImage: false` when the page has no image.
- `generateImage($prompt, $style, $aspect)` makes only an image and returns an array with `url`, `base64` or `error`; it never throws and always uses the OpenAI image API.
- A failing text call throws `RuntimeException`. A failing image does not: the text comes back with `hasError()` true. Check `hasError()` before saving a result as complete.
- `text` is HTML written by a model. Sanitise it before rendering it unescaped.
- Settings live under the config key `ai-generator` (file `config/ai-generator.php`). Read them through `Darvis\LaravelAiGenerator\Support\AiGeneratorConfig` (`defaultLanguage()`, `openAiModel()`, `openAiApiKey()`), not with `config()`.
- Another provider: implement `Darvis\LaravelAiGenerator\Contracts\AiContentDriver` and replace the binding in your own service provider with `$this->app->singleton(AiContentDriver::class, ...)`. Don't use `extend()`: the package binding throws on a driver name it does not know before your extender runs.
- Generating is slow, tens of seconds with an image. Do it in a queued job, not in a web request.
- In tests, never call OpenAI: use `Http::fake()` or bind a fake `AiContentDriver`.

@verbatim
<code-snippet name="Generate an article in a queued job" lang="php">
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;

$result = AiGenerator::generate(new ContentRequest(
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
