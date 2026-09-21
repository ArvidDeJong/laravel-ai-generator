---
title: "Configuration"
nav_order: 4
description: "Every key in config/ai-generator.php with its environment variable and default: models, timeout, base URL, default language, tone, reading level and length."
---

# Configuration

You configure the package with environment variables in `.env`. Only `OPENAI_API_KEY` is required.

## Every setting

| Config key | Environment variable | Default | What it does |
| --- | --- | --- | --- |
| `default_language` | `AI_GENERATOR_LANGUAGE` | `nl` | Language of a request without `language`, as an ISO 639-1 code. |
| `defaults.max_words` | `AI_GENERATOR_MAX_WORDS` | `900` | Word limit of a request without `maxWords`. |
| `defaults.reading_level` | `AI_GENERATOR_LEVEL` | `general` | Reading level of a request without `readingLevel`: `simple`, `general` or `expert`. |
| `defaults.tone` | `AI_GENERATOR_TONE` | `informal` | Tone of a request without `tone`: `informal`, `neutral` or `formal`. |
| `driver` | `AI_GENERATOR_DRIVER` | `openai` | The driver that writes the text. The package knows `openai`; see [Custom drivers](custom-drivers.md) for your own. |
| `drivers.openai.api_key` | `OPENAI_API_KEY` | none | Your OpenAI API key. Required. |
| `drivers.openai.base_url` | `OPENAI_BASE_URL` | `https://api.openai.com/v1` | Where the requests go. See [Another endpoint](#another-endpoint). |
| `drivers.openai.image_model` | `OPENAI_IMAGE_MODEL` | `gpt-image-1` | Model for the image, in `generate()` and in `generateImage()`. |
| `drivers.openai.model` | `OPENAI_MODEL` | `gpt-4.1-mini` | Model for the text. |
| `drivers.openai.temperature` | `OPENAI_TEMPERATURE` | `0.7` | The `temperature` value sent with every text request. |
| `drivers.openai.timeout` | `OPENAI_TIMEOUT` | `45` | Seconds one request inside `generate()` may take, for the text and for the image. |

The default language is `nl`, Dutch. Set `AI_GENERATOR_LANGUAGE=en` when your site is in English.

`OPENAI_TIMEOUT` counts per attempt, and both requests inside `generate()` are tried twice.
`generateImage()` does not use it: that method has a fixed timeout of 120 seconds.

## An example .env

```env
# Required
OPENAI_API_KEY=your-api-key-here

# Optional: OpenAI
OPENAI_MODEL=gpt-4.1-mini
OPENAI_IMAGE_MODEL=gpt-image-1
OPENAI_TEMPERATURE=0.7
OPENAI_TIMEOUT=45

# Optional: defaults for a request
AI_GENERATOR_LANGUAGE=en
AI_GENERATOR_TONE=neutral
AI_GENERATOR_LEVEL=general
AI_GENERATOR_MAX_WORDS=600
```

After a change in `.env` on a server that caches its config, run `php artisan config:clear` or
`php artisan config:cache` again. Until then the application keeps the old values.

## The config file

You don't need the file in your application. Publish it when you want to set a value in PHP:

```bash
php artisan vendor:publish --tag=ai-generator-config
```

You get `config/ai-generator.php`, with the keys in alphabetical order within every group:

```php
return [
    'default_language' => env('AI_GENERATOR_LANGUAGE', 'nl'),

    'defaults' => [
        'max_words' => env('AI_GENERATOR_MAX_WORDS', 900),
        'reading_level' => env('AI_GENERATOR_LEVEL', 'general'),
        'tone' => env('AI_GENERATOR_TONE', 'informal'),
    ],

    'driver' => env('AI_GENERATOR_DRIVER', 'openai'),

    'drivers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
            'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
            'timeout' => env('OPENAI_TIMEOUT', 45),
        ],
    ],
];
```

A value you write into this file instead of `env(...)` wins over `.env`. Keep that in mind when a
change in `.env` seems to do nothing.

## A request option beats the config

The config only fills what a request leaves empty. Pass the value in the request when it differs per
text:

```php
use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;

$result = app(AiGenerator::class)->generate(new ContentRequest(
    topic: 'Your topic',
    tone: 'formal',
    readingLevel: 'expert',
    includeImage: false,
));
```

## Read a setting in your own code

The package reads its config in one class, `Darvis\LaravelAiGenerator\Support\AiGeneratorConfig`.
Use the same class when your code needs a package setting, so the default is the same everywhere:

```php
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;

AiGeneratorConfig::defaultLanguage(); // 'nl'
AiGeneratorConfig::openAiModel();     // 'gpt-4.1-mini'
AiGeneratorConfig::openAiApiKey();    // null when the key is empty or not set
```

The [API reference](api-reference.md#aigeneratorconfig) lists every accessor.

## Another endpoint

`OPENAI_BASE_URL` sends the requests to another host, for example a proxy of your own. The package
adds `/responses` for the text and `/images/generations` for the image to that URL, and sends the
API key as an `Authorization: Bearer` header. The endpoint has to accept exactly that: the OpenAI
Responses API with a JSON schema, and the OpenAI image API.

A trailing slash in the URL is removed.
