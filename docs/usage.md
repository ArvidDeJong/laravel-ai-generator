---
title: "Usage"
nav_order: 3
description: "A complete example that generates an article in Laravel, every ContentRequest option, the ContentResult, saving the image, error handling and queued jobs."
---

# Usage

## Generate your first article

This example is an Artisan command, so you can run it without a database, a model or a view. Create
the file below.

**`app/Console/Commands/GenerateArticle.php`**

```php
<?php

namespace App\Console\Commands;

use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateArticle extends Command
{
    protected $signature = 'article:generate {topic : What the article is about}';

    protected $description = 'Generate an article with darvis/laravel-ai-generator';

    public function handle(AiGenerator $generator): int
    {
        try {
            $result = $generator->generate(new ContentRequest(
                topic: $this->argument('topic'),
                language: 'en',
                maxWords: 300,
                includeImage: false,
            ));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($result->title);
        $this->line($result->intro);
        $this->line($result->text);
        $this->line('SEO title: '.$result->seoTitle);
        $this->line('Meta description: '.$result->seoDescription);

        return self::SUCCESS;
    }
}
```

Run it:

```bash
php artisan article:generate "How to start a vegetable garden"
```

Laravel injects the `AiGenerator` into `handle()`. The package sends one text request to OpenAI and
the command prints the title, the intro, the HTML text and the two SEO fields. When the call fails,
the command prints the message of the exception; [Troubleshooting](troubleshooting.md) lists every
message.

`includeImage: false` is there on purpose. Read [the next section](#image-default)
before you remove it.

## The image is on by default and costs a second call {#image-default}

`includeImage` defaults to `true`. With the OpenAI driver that means:

1. The model also writes an English image prompt, which you get back as `imagePrompt`.
2. The package sends a second request, to the OpenAI image API, with that prompt.

OpenAI bills that second request too, and you wait for it. Pass `includeImage: false` for every text
that does not need an image.

## Get the generator in your own code

All three give you the same object, a singleton of `Darvis\LaravelAiGenerator\AiGenerator`.

**Dependency injection**: type hint it in a constructor, a controller method or the `handle()` method
of a command or job. Laravel passes it in.

```php
use Darvis\LaravelAiGenerator\AiGenerator;

public function handle(AiGenerator $generator): void
{
    // $generator->generate(...)
}
```

**The service container**, anywhere else:

```php
use Darvis\LaravelAiGenerator\AiGenerator;

$generator = app(AiGenerator::class);
```

**The facade**, a static shortcut to the same object:

```php
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;

$result = AiGenerator::generate(new ContentRequest(topic: 'Your topic'));
```

The facade has no global alias, so import `Darvis\LaravelAiGenerator\Facades\AiGenerator`. The
service class and the facade are both called `AiGenerator`: import one of them per file.

## Every request option

`ContentRequest` uses named arguments. Only `topic` is required.

| Argument | Type | When you leave it out | What it does |
| --- | --- | --- | --- |
| `topic` | `string` | required | What the text is about. |
| `language` | `?string` | config `default_language`, `nl` | ISO 639-1 code such as `nl`, `en`, `de` or `fr`. |
| `audience` | `?string` | not mentioned in the prompt | Who the text is for, in your own words. |
| `tone` | `?string` | config `defaults.tone`, `informal` | `informal`, `neutral` or `formal`. |
| `readingLevel` | `?string` | config `defaults.reading_level`, `general` | `simple`, `general` or `expert`. |
| `keywords` | `?array` | none | Words the model is asked to use naturally. |
| `cta` | `?string` | not mentioned in the prompt | A call to action to include. |
| `brand` | `?string` | not mentioned in the prompt | Brand or organisation name, for context. |
| `maxWords` | `?int` | config `defaults.max_words`, `900` | Approximate word limit for the main text, without the intro. |
| `includeImage` | `bool` | `true` | Generate an image prompt and an image. A second, billed call. |
| `imageStyle` | `?string` | `photo` | `photo`, `illustration`, `flat` or `3d`. |
| `imageAspect` | `?string` | `16:9` | `1:1`, `4:5` or `16:9`. See [the image size](#image-size). |

The package does not validate these values. They go into the prompt as you wrote them, so a typing
error in `tone` reaches the model unchanged.

For Dutch, German and French the prompt also names the form of address: `je/jij` for informal Dutch,
`du` or `Sie` for German, `tu` or `vous` for French.

A request with everything filled in:

```php
use Darvis\LaravelAiGenerator\ContentRequest;

$request = new ContentRequest(
    topic: 'Sustainable packaging for web shops',
    language: 'en',
    audience: 'web shop owners',
    tone: 'formal',
    readingLevel: 'expert',
    keywords: ['sustainable', 'packaging', 'shipping'],
    cta: 'Contact us for a free consultation',
    brand: 'EcoPack Solutions',
    maxWords: 800,
    includeImage: true,
    imageStyle: 'photo',
    imageAspect: '16:9',
);
```

## What comes back

`generate()` returns a `ContentResult`. Every text field is trimmed.

| Property | Type | Content |
| --- | --- | --- |
| `title` | `string` | The headline. |
| `intro` | `string` | Two to four sentences, plain text. |
| `text` | `string` | The main text as HTML with `h2`, `p`, `ul` and `li`. |
| `seoTitle` | `string` | The model is asked for at most 60 characters. |
| `seoDescription` | `string` | The model is asked for at most 155 characters. |
| `imagePrompt` | `?string` | The English image prompt, `null` with `includeImage: false`. |
| `imageUrl` | `?string` | URL of the image, when the image API returned one. |
| `imageBase64` | `?string` | The image as base64, when the image API returned that. |
| `errorMessage` | `?string` | Filled when the text succeeded but the image failed. |

Two helpers: `hasImage()` is `true` when `imageUrl` or `imageBase64` is filled, `hasError()` is
`true` when `errorMessage` is filled.

The lengths of the SEO fields are an instruction to the model, not a guarantee. Check the length
yourself when your database column is limited.

`ContentRequest` and `ContentResult` are immutable: their properties are `readonly`. Build a new
object instead of changing one.

## Sanitise the HTML before you render it {#sanitise-html}

`text` is HTML written by a language model. Treat it as untrusted input: run it through an HTML
sanitiser that only allows the tags you expect (`h2`, `p`, `ul`, `li`) before you render it
unescaped. The package does not do this for you.

## Save the image

The image arrives as `imageBase64` or as `imageUrl`, depending on what the image model returns.
Handle both, and store the file yourself: the package does not download or save anything.

**In the code that receives the result, for example a job or a controller**

```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

if ($result->hasImage()) {
    $binary = $result->imageBase64 !== null
        ? base64_decode($result->imageBase64)
        : Http::get($result->imageUrl)->body();

    Storage::disk('public')->put('articles/'.$post->id.'.png', $binary);
}
```

This writes the image to the `public` disk. The package does not report the file type; check what
your image model returns before you rely on the `.png` extension.

### The image size follows the aspect ratio only with DALL-E 3 {#image-size}

When `OPENAI_IMAGE_MODEL` contains `dall-e-3`, the package asks for `1024x1024` (`1:1`), `1024x1792`
(`4:5`) or `1792x1024` (every other value, including `16:9`). For every other image model, including
the default `gpt-image-1`, it asks for a square `1024x1024` image.

For a model name that contains `dall-e`, the package asks for base64 output, so `imageBase64` is the
field that is filled.

## Handle errors

There are two kinds of failure, and you handle them differently.

```php
use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;
use Illuminate\Support\Facades\Log;
use RuntimeException;

try {
    $result = app(AiGenerator::class)->generate(new ContentRequest(topic: 'Your topic'));
} catch (RuntimeException $e) {
    // The text failed: no key, no connection, an HTTP error or an unreadable answer.
    Log::error('Generation failed: '.$e->getMessage());

    return;
}

if ($result->hasError()) {
    // The text is complete, only the image failed.
    Log::warning('Image failed: '.$result->errorMessage);
}
```

- **The text fails**: `generate()` throws a `RuntimeException`. The package tries the text request
  twice, 250 milliseconds apart, before it throws.
- **The image fails**: nothing is thrown. You get the full text, `hasImage()` is `false`,
  `hasError()` is `true` and `errorMessage` starts with `OpenAI Image Error:`. The image request is
  also tried twice.

Check `hasError()` before you mark a result as complete.

## Generate only an image

`generateImage()` skips the text and only makes an image.

```php
use Darvis\LaravelAiGenerator\AiGenerator;
use Illuminate\Support\Facades\Log;

$image = app(AiGenerator::class)->generateImage(
    'A lighthouse at dusk',
    style: 'illustration',   // photo, illustration, flat or 3d
    aspect: '16:9',          // 1:1, 4:5 or 16:9
);

if (isset($image['error'])) {
    Log::warning($image['error']);
} else {
    $base64 = $image['base64'];  // string or null
    $url = $image['url'];        // string or null
}
```

What you need to know:

- It returns an array and does not throw. On success the array has the keys `url` and `base64`, one
  of them filled. On failure it has only the key `error`.
- It always calls the OpenAI image API with `OPENAI_IMAGE_MODEL` and `OPENAI_API_KEY`, also when a
  [custom driver](custom-drivers.md) writes your text.
- The style becomes a prefix of your prompt, for example `Digital illustration of`, and
  `No text or watermarks.` is added at the end. Any other style value sends your prompt unchanged.
- It waits up to 120 seconds and tries once. It does not use `OPENAI_TIMEOUT`.

## Generate in a queued job {#queued-job}

A queued job is a task that a background worker runs, so the visitor does not wait for it; see
[Queues](https://laravel.com/docs/queues) in the Laravel documentation.

Every request inside `generate()` waits up to `OPENAI_TIMEOUT` seconds, 45 by default, and is tried
twice. With an image that is four waits of 45 seconds in the worst case, which is longer than a web
request should take.

This example assumes a `Post` model with the columns used below.

**`app/Jobs/GenerateArticleContent.php`**

```php
<?php

namespace App\Jobs;

use App\Models\Post;
use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateArticleContent implements ShouldQueue
{
    use Queueable;

    /** Seconds the job may run: above the worst case of the calls inside generate(). */
    public int $timeout = 200;

    /** Don't run a failed generation again by itself: every attempt is billed. */
    public int $tries = 1;

    public function __construct(
        public Post $post,
        public string $topic,
    ) {}

    public function handle(AiGenerator $generator): void
    {
        $result = $generator->generate(new ContentRequest(
            topic: $this->topic,
            language: 'en',
            includeImage: false,
        ));

        $this->post->update([
            'title' => $result->title,
            'excerpt' => $result->intro,
            'content' => $result->text,
            'meta_title' => $result->seoTitle,
            'meta_description' => $result->seoDescription,
        ]);
    }
}
```

**Where you start the generation, for example a controller**

```php
use App\Jobs\GenerateArticleContent;

GenerateArticleContent::dispatch($post, 'How to start a vegetable garden');
```

The worker calls `handle()`, and the post is filled when the text is ready. When `generate()` throws,
the job fails with that message. Keep the `retry_after` of your queue connection above the job's
`$timeout`; the Laravel queue documentation explains both settings.
