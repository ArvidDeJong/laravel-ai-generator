---
title: "Configuration"
nav_order: 6
description: "Every key in config/ai-generator.php with its environment variable and default: providers, models, image driver, fallbacks, MCP, timeouts, language, tone and length."
---

# Configuration

You configure the package with environment variables in `.env`. The only thing you need is the API
key of at least one provider, and `AI_GENERATOR_DRIVER` when that provider is not OpenAI. The wizard,
`php artisan ai-generator:install`, writes these for you; see [Installation](installation.md).

## General settings

| Config key | Environment variable | Default | What it does |
| --- | --- | --- | --- |
| `default_language` | `AI_GENERATOR_LANGUAGE` | `nl` | Language of a request without `language`, as an ISO 639-1 code. |
| `defaults.max_words` | `AI_GENERATOR_MAX_WORDS` | `900` | Word limit of a request without `maxWords`. |
| `defaults.reading_level` | `AI_GENERATOR_LEVEL` | `general` | Reading level of a request without `readingLevel`: `simple`, `general` or `expert`. |
| `defaults.tone` | `AI_GENERATOR_TONE` | `informal` | Tone of a request without `tone`: `informal`, `neutral` or `formal`. |
| `driver` | `AI_GENERATOR_DRIVER` | `openai` | The driver that writes the text: `openai`, `anthropic`, `gemini` or `xai`, or an alias (`chatgpt`, `claude`, `google`, `grok`). See [Multiple providers](providers.md). |
| `fallbacks` | `AI_GENERATOR_FALLBACKS` | empty | Drivers to try in order when the text call fails, comma separated, for example `openai,gemini`. |
| `image_driver` | `AI_GENERATOR_IMAGE_DRIVER` | empty | The driver that makes images: `openai`, `gemini` or `xai`. Empty follows the text driver, and uses OpenAI when the text driver cannot make images. |
| `mcp.enabled` | `AI_GENERATOR_MCP` | `true` | Register the local [MCP server](mcp.md) when `laravel/mcp` is installed. |
| `mcp.handle` | `AI_GENERATOR_MCP_HANDLE` | `ai-generator` | The name of that server, for `php artisan mcp:start`. |

The default language is `nl`, Dutch. Set `AI_GENERATOR_LANGUAGE=en` when your site is in English.

## Settings per provider

Every provider has its own block under `drivers`. You only fill in the providers you use.

| Setting | OpenAI | Claude (Anthropic) | Gemini (Google) | Grok (xAI) |
| --- | --- | --- | --- | --- |
| API key | `OPENAI_API_KEY` | `ANTHROPIC_API_KEY` | `GEMINI_API_KEY` | `XAI_API_KEY` |
| Text model | `OPENAI_MODEL`, default `gpt-4.1-mini` | `ANTHROPIC_MODEL`, default `claude-sonnet-5` | `GEMINI_MODEL`, default `gemini-3.8-flash` | `XAI_MODEL`, default `grok-4.7` |
| Image model | `OPENAI_IMAGE_MODEL`, default `gpt-image-2` | none, Claude makes no images | `GEMINI_IMAGE_MODEL`, default `gemini-3.1-flash-image` | `XAI_IMAGE_MODEL`, default `grok-imagine-image-2.0` |
| Temperature | `OPENAI_TEMPERATURE`, default `0.7` | `ANTHROPIC_TEMPERATURE`, default none | `GEMINI_TEMPERATURE`, default none | `XAI_TEMPERATURE`, default none |
| Timeout in seconds | `OPENAI_TIMEOUT`, default `45` | `ANTHROPIC_TIMEOUT`, default `45` | `GEMINI_TIMEOUT`, default `45` | `XAI_TIMEOUT`, default `45` |
| Base URL | `OPENAI_BASE_URL`, default `https://api.openai.com/v1` | `ANTHROPIC_BASE_URL`, default `https://api.anthropic.com/v1` | `GEMINI_BASE_URL`, default `https://generativelanguage.googleapis.com/v1beta` | `XAI_BASE_URL`, default `https://api.x.ai/v1` |

Claude has two more settings:

| Config key | Environment variable | Default | What it does |
| --- | --- | --- | --- |
| `drivers.anthropic.max_tokens` | `ANTHROPIC_MAX_TOKENS` | `8192` | The maximum length of Claude's answer, in tokens. Claude requires a limit. |
| `drivers.anthropic.version` | `ANTHROPIC_VERSION` | `2023-06-01` | The value of the `anthropic-version` header. |

**Temperature.** An empty temperature sends none. The newest Claude models reject a temperature, so
none is sent to Claude, Gemini or Grok unless you set one. OpenAI gets `0.7`. OpenAI reasoning models
reject a temperature too; set `OPENAI_TEMPERATURE=` (empty) when you use one.

**Timeout.** It counts per attempt, and both requests inside `generate()` (text and image) are tried
twice. `generateImage()` does not use it: that method has a fixed timeout of 120 seconds and tries
once.

**Models age.** Providers retire models. OpenAI shuts down `gpt-image-1` on 2026-10-23, so since
1.3.0 the default image model is `gpt-image-2`, the replacement OpenAI names. Is `OPENAI_IMAGE_MODEL`
set to `gpt-image-1` in your `.env`? Change it to `gpt-image-2`, or remove the line to follow the
default. The wizard lists the image models your key may use.

## An example .env

```env
# The keys of the providers you use
ANTHROPIC_API_KEY=your-claude-key
OPENAI_API_KEY=your-openai-key

# Who writes, who draws, who takes over
AI_GENERATOR_DRIVER=anthropic
AI_GENERATOR_IMAGE_DRIVER=openai
AI_GENERATOR_FALLBACKS=openai

# Optional: models
ANTHROPIC_MODEL=claude-sonnet-5
OPENAI_MODEL=gpt-4.1-mini
OPENAI_IMAGE_MODEL=gpt-image-2

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
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 8192),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
            'temperature' => env('ANTHROPIC_TEMPERATURE'),
            'timeout' => env('ANTHROPIC_TIMEOUT', 45),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image'),
            'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
            'temperature' => env('GEMINI_TEMPERATURE'),
            'timeout' => env('GEMINI_TIMEOUT', 45),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
            'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
            'timeout' => env('OPENAI_TIMEOUT', 45),
        ],
        'xai' => [
            'api_key' => env('XAI_API_KEY'),
            'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
            'image_model' => env('XAI_IMAGE_MODEL', 'grok-imagine-image-2.0'),
            'model' => env('XAI_MODEL', 'grok-4.7'),
            'temperature' => env('XAI_TEMPERATURE'),
            'timeout' => env('XAI_TIMEOUT', 45),
        ],
    ],

    'fallbacks' => env('AI_GENERATOR_FALLBACKS', ''),

    'image_driver' => env('AI_GENERATOR_IMAGE_DRIVER'),

    'mcp' => [
        'enabled' => env('AI_GENERATOR_MCP', true),
        'handle' => env('AI_GENERATOR_MCP_HANDLE', 'ai-generator'),
    ],

];
```

A value you write into this file instead of `env(...)` wins over `.env`. Keep that in mind when a
change in `.env` seems to do nothing.

A config file you published with an older version keeps working: no key was renamed or removed. A
provider block that is missing from your file falls back to the defaults of the package. Publish
again (with `--force`, after saving your changes) to see the new blocks.

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
use Darvis\LaravelAiGenerator\Support\Provider;

AiGeneratorConfig::defaultLanguage();            // 'nl'
AiGeneratorConfig::model(Provider::Anthropic);   // 'claude-sonnet-5'
AiGeneratorConfig::apiKey(Provider::OpenAi);     // null when the key is empty or not set
```

The [API reference](api-reference.md#aigeneratorconfig) lists every accessor.

## Another endpoint

`<PREFIX>_BASE_URL` sends the requests of a provider to another host, for example a proxy of your
own. The endpoint has to accept exactly what the provider accepts:

- **OpenAI:** `/responses` for the text and `/images/generations` for the image, with the key as an
  `Authorization: Bearer` header.
- **Claude:** `/messages`, with the key in an `x-api-key` header.
- **Gemini:** `/models/<model>:generateContent`, with the key in an `x-goog-api-key` header.
- **Grok:** `/chat/completions` and `/images/generations`, with the key as a Bearer token.

A trailing slash in the URL is removed.
