---
title: "Multiple providers"
nav_order: 5
description: "Use Claude, ChatGPT, Gemini and Grok side by side: the default driver, another provider per call, which provider makes the images, and fallbacks when a provider fails."
---

# Multiple providers

The package ships a driver for four AI providers. You can use one of them, or several at the same
time. A common setup: you have a Claude key and a ChatGPT key, Claude writes the text and OpenAI
makes the images, and when Claude is down OpenAI writes the text too.

## The drivers

| Driver | Also called | Provider | Text | Images | Default text model | Default image model |
| --- | --- | --- | --- | --- | --- | --- |
| `openai` | `chatgpt`, `gpt` | OpenAI | yes | yes | `gpt-4.1-mini` | `gpt-image-1` |
| `anthropic` | `claude` | Anthropic | yes | **no** | `claude-sonnet-5` | none |
| `gemini` | `google` | Google | yes | yes | `gemini-3.8-flash` | `gemini-3.1-flash-image` |
| `xai` | `grok` | xAI | yes | yes | `grok-4.7` | `grok-imagine-image-2.0` |

You can use the name or the alias anywhere a driver name is asked, in `.env` and in code. Each
driver needs its own API key; see [Get your API keys](api-keys.md). Change a model with the variable
`<PREFIX>_MODEL` or `<PREFIX>_IMAGE_MODEL`, for example `ANTHROPIC_MODEL=claude-opus-5-5`. The
wizard lists the models your key may use. [Configuration](configuration.md) has every setting.

## The default driver

`AI_GENERATOR_DRIVER` decides who writes the text when you don't say otherwise:

```env
AI_GENERATOR_DRIVER=anthropic
```

The default is `openai`, so an application that only has `OPENAI_API_KEY` keeps working as before.

## Another provider for one call

`using()` gives you a generator that writes with another driver. The second argument picks another
model of that provider; leave it out for the configured one.

```php
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;

$request = new ContentRequest(topic: 'Winter tyres', language: 'en', includeImage: false);

$fromClaude = AiGenerator::using('anthropic')->generate($request);
$fromGpt = AiGenerator::using('openai', 'gpt-6-luna')->generate($request);
```

The same works on an injected `Darvis\LaravelAiGenerator\AiGenerator`:
`$generator->using('gemini')->generate($request)`.

`using()` throws `Unsupported AI driver: ...` for a name the package does not know.

## Which provider wrote this?

Every result from a built-in driver says which driver and model wrote the text:

```php
$result->driver; // 'anthropic'
$result->model;  // 'claude-sonnet-5'
```

Both are `null` for a [custom driver](custom-drivers.md).

## Which provider makes the images

Claude cannot make images. The package decides who makes the image like this:

1. `AI_GENERATOR_IMAGE_DRIVER` when it is set: `openai`, `gemini` or `xai`.
2. Otherwise the text driver itself, when it can make images (OpenAI, Gemini, Grok).
3. Otherwise OpenAI. So with Claude as text driver, the image comes from OpenAI and needs
   `OPENAI_API_KEY`.

```env
# Claude writes, Gemini draws
AI_GENERATOR_DRIVER=anthropic
AI_GENERATOR_IMAGE_DRIVER=gemini
```

When the image driver cannot make images or has no key, the text still comes back. `hasError()` is
then `true` and `errorMessage` says why. See [Troubleshooting](troubleshooting.md#no-image).

For an image without text, pass the driver as fourth argument, or leave it out for the rules above:

```php
$image = AiGenerator::generateImage('A lighthouse at dusk', 'photo', '16:9', 'gemini');
```

### The aspect ratio per provider

- **OpenAI:** only a `dall-e-3` model follows `imageAspect`; other models make a square image. See
  [the image size](usage.md#image-size).
- **Gemini:** the ratio (`1:1`, `4:5`, `16:9`) is passed to the model.
- **Grok:** `1:1` and `16:9` are passed on. Grok has no `4:5`, so the package asks for `3:4`, the
  nearest portrait ratio.

## Fallbacks: another provider takes over

Set a list of drivers to try, in order, when the text call of the default driver fails:

```env
AI_GENERATOR_DRIVER=anthropic
AI_GENERATOR_FALLBACKS=openai,gemini
```

- A driver in the list without an API key is skipped.
- The default driver itself is skipped when it is in the list.
- When every driver fails, you get one `RuntimeException`:
  `Every AI driver failed. anthropic: ... | openai: ... | gemini: ...`.
- `using()` never falls back. When you ask for a provider by name, a failure is reported instead of
  answered by another provider.
- A fallback only happens for the text. A failed image never throws; see above.

Each attempt that reaches a provider can be billed, so keep the list short.

## Example: a Claude key and a ChatGPT key

Claude writes, OpenAI makes the images and takes over when Claude fails:

```env
ANTHROPIC_API_KEY=your-claude-key
OPENAI_API_KEY=your-openai-key

AI_GENERATOR_DRIVER=anthropic
AI_GENERATOR_IMAGE_DRIVER=openai
AI_GENERATOR_FALLBACKS=openai
```

The wizard, `php artisan ai-generator:install`, writes exactly this when you pick both providers.

## Settings that differ per provider

- **Temperature.** A lower value makes the text more predictable, a higher value more varied. The
  newest Claude models reject a temperature, so by default the package sends none to Claude, Gemini
  and Grok. OpenAI gets `0.7` by default. OpenAI reasoning models reject a temperature too: set
  `OPENAI_TEMPERATURE=` (empty) for those.
- **Maximum length of Claude's answer.** Claude needs an upper limit of tokens. The default,
  `ANTHROPIC_MAX_TOKENS=8192`, is plenty for 900 words. When you ask for very long texts and see
  `Claude stopped before the answer was complete.`, raise it.
- **Timeouts.** Every provider has its own `<PREFIX>_TIMEOUT`, 45 seconds by default.
