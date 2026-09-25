---
title: "Troubleshooting"
nav_order: 10
description: "Every error message of darvis/laravel-ai-generator with its cause and fix: missing or refused API keys, failed requests per provider, no image, fallbacks, stale config."
---

# Troubleshooting

Each section is a symptom, then the cause, then the fix. The messages are quoted literally from the
package, so you can search this page for the text you see.

Start with the check that costs nothing: `php artisan ai-generator:status`. It shows every provider,
whether its key is set and whether the provider accepts it. See
[Check that it works](installation.md#check-that-it-works).

In the messages below, **Provider** is `OpenAI`, `Claude`, `Gemini` or `Grok`, and the key variable is
`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY` or `XAI_API_KEY`.

## "OPENAI_API_KEY is not set." (or ANTHROPIC_, GEMINI_, XAI_) {#api-key-not-set}

**Symptom.** `generate()` throws a `RuntimeException` with this message, or `generateImage()` returns
`['error' => 'OPENAI_API_KEY is not set.']`. No request was sent. The variable in the message tells
you which provider was asked.

**Cause.** The API key of that provider is empty. Usually one of these:

1. The key is missing from `.env`, or it is in `.env.example` instead of `.env`. Run
   `php artisan ai-generator:install`, or see [Get your API keys](api-keys.md).
2. The config is cached. With a cached config Laravel does not read `.env` any more.
3. You published `config/ai-generator.php` and changed the `api_key` line.
4. A queue worker was started before you added the key. A worker keeps the application, and its
   config, in memory.

**Fix.**

```bash
php artisan config:clear
php artisan queue:restart
```

Then check that `config/ai-generator.php`, when it exists in your application, still has
`'api_key' => env('OPENAI_API_KEY')` (or the variable of your provider). A config file published
with an older version has no block for Claude, Gemini or Grok; that is fine, the package then uses its
own defaults and reads the key from `.env` all the same.

Did you not expect this provider to be called at all? Look at `AI_GENERATOR_DRIVER`,
`AI_GENERATOR_IMAGE_DRIVER` and `AI_GENERATOR_FALLBACKS`. With Claude as text driver the image comes
from OpenAI unless you set another image driver.

## "... refused the API key (HTTP 401)." {#key-refused}

**Symptom.** The wizard or `php artisan ai-generator:status` shows, for example,
`Claude refused the API key (HTTP 401).` The status may also be 400 or 403.

**Cause.** The provider does not accept the key: a typing error, a revoked key, a key of another
account, or (with Gemini) an old key type that Google no longer accepts.

**Fix.** Create a new key and paste it again; [Get your API keys](api-keys.md) shows where. Check
that there are no spaces or quotes around the key in `.env`. Some providers also refuse calls when
your account has no credits.

## "OpenAI request failed: HTTP request returned status code ..." (or Claude, Gemini, Grok)

**Symptom.** `generate()` throws a `RuntimeException` that starts with `OpenAI request failed:`,
`Claude request failed:`, `Gemini request failed:` or `Grok request failed:`, followed by Laravel's
message `HTTP request returned status code` with the status and the start of the provider's answer.

**Cause.** The provider answered with a 4xx or 5xx status, on both attempts. The answer of the
provider in the message says why. The status tells you where to look:

| Status | Look at |
| --- | --- |
| 401 or 403 (unauthorized, forbidden) | The API key of that provider. |
| 400 or 404 (bad request, not found) | The model name (`OPENAI_MODEL`, `ANTHROPIC_MODEL`, `GEMINI_MODEL`, `XAI_MODEL`), the temperature and the base URL. A model that was retired gives a 404. A model that rejects a temperature gives a 400: leave `ANTHROPIC_TEMPERATURE` empty, and set `OPENAI_TEMPERATURE=` (empty) for OpenAI reasoning models. |
| 402 or 429 (payment required, too many requests) | The credits and limits of your account at the provider. |
| 5xx (server error) | The other side. Try again later, or set [fallbacks](providers.md#fallbacks-another-provider-takes-over). |

**Fix.** Read the rest of the message, correct the setting it points at, and run
`php artisan config:clear` when your config is cached.

## "OpenAI connection failed: ..." (or Claude, Gemini, Grok)

**Symptom.** `generate()` throws a `RuntimeException` that starts with `OpenAI connection failed:`,
or the same with `Claude`, `Gemini` or `Grok`.

**Cause.** No answer arrived within the timeout of that provider (45 seconds by default), on both
attempts, or the host could not be reached at all.

**Fix.**

- Raise the timeout in `.env`, for example `OPENAI_TIMEOUT=90` or `ANTHROPIC_TIMEOUT=90`.
- Check the base URL (`OPENAI_BASE_URL` and so on) when you changed it.
- Check that the server may make outgoing HTTPS requests.
- Generate in a [queued job](usage.md#queued-job), so a slow answer does not block a visitor.

## "... returned non-JSON output (unexpected)." or an answer without text

**Symptom.** `generate()` throws a `RuntimeException` with one of these messages:

- `OpenAI returned non-JSON output (unexpected).` (or `Claude`, `Gemini`, `Grok`)
- `OpenAI response did not include output_text.`
- `Claude response did not include a text block.`
- `Gemini response did not include text (finish reason: ...).`
- `Grok response did not include message content.`

**Cause.** The request succeeded, but the answer did not have the shape the driver expects, or the
text in it was not the JSON object the package asked for. For Gemini the finish reason says why it
stopped, for example `SAFETY` or `MAX_TOKENS`.

**Fix.** Check the model and the base URL of that provider: the model has to support structured
output with a JSON schema. Try the default model. In a test, check the shape of your fake on
[Testing](testing.md).

## "Claude stopped before the answer was complete. Raise ANTHROPIC_MAX_TOKENS or lower maxWords."

**Cause.** Claude reached its maximum answer length, `ANTHROPIC_MAX_TOKENS` (8192 by default), before
the JSON was complete.

**Fix.** Raise `ANTHROPIC_MAX_TOKENS`, for example to `16000`, or ask for fewer words with `maxWords`.

## "Claude declined to write about this topic." or "Grok declined to write about this topic: ..."

**Cause.** The model refused the request, usually because of the topic.

**Fix.** Rephrase the topic, or try another provider with `AiGenerator::using()`.

## "Gemini blocked the prompt: ..."

**Cause.** Google's safety filter blocked the prompt before the model answered. The reason follows
the colon, for example `SAFETY`.

**Fix.** Rephrase the topic, or try another provider.

## "Every AI driver failed. ..."

**Symptom.** A `RuntimeException` like
`Every AI driver failed. anthropic: Claude request failed: ... | openai: OpenAI connection failed: ...`.

**Cause.** You set `AI_GENERATOR_FALLBACKS`, the default driver failed and every fallback driver
failed too. Every part of the message is the error of one driver.

**Fix.** Handle each part with the section of that message on this page. `getPrevious()` gives the
exception of the default driver.

## The text is there, but there is no image {#no-image}

**Symptom.** `hasImage()` is `false`.

**Cause and fix.** Look at `errorMessage` and `imagePrompt`:

| What you see | Cause | Fix |
| --- | --- | --- |
| `errorMessage` starts with `OpenAI Image Error:`, `Gemini Image Error:` or `Grok Image Error:` | The image request failed on both attempts. The rest of the message is the reason: a missing key, the HTTP status or the timeout. | A missing key: set the key of the image provider, or choose another one with `AI_GENERATOR_IMAGE_DRIVER`. A status: check the image model (`OPENAI_IMAGE_MODEL` and so on) and whether your account may use it. A timeout: raise the timeout of that provider. |
| `errorMessage` is `OpenAI Image Error: OPENAI_API_KEY is not set.` while you write with Claude | Claude makes no images, so the package asks OpenAI. | Set `OPENAI_API_KEY`, or `AI_GENERATOR_IMAGE_DRIVER=gemini` or `xai` with that key, or pass `includeImage: false`. |
| `errorMessage` ends with `cannot generate images. Set AI_GENERATOR_IMAGE_DRIVER to openai, gemini or xai.` | `AI_GENERATOR_IMAGE_DRIVER` names a driver without images, such as `anthropic`. | Set it to `openai`, `gemini` or `xai`. |
| `errorMessage` is `Gemini Image Error: Gemini returned no image.` | Gemini answered, but without an image part. | Try again, or use another `GEMINI_IMAGE_MODEL`. |
| `imagePrompt` is `null` | The request had `includeImage: false`. | Pass `includeImage: true` or leave the argument out. |
| `imagePrompt` is an empty string and there is no error | The model wrote no image prompt, so the package sent no image request. | Generate again, or call `generateImage()` with a prompt of your own. |
| No image, no error, and you use a custom driver | The package does not make the image for a custom driver. | Let your driver do it, or call `generateImage()`; see [Custom drivers](custom-drivers.md). |

A failed image never throws. The text is complete, so you can save it and try the image again with
`generateImage($result->imagePrompt)`.

## generateImage() returns "... cannot generate images. Use openai, gemini or xai as image driver."

**Cause.** You passed a driver without images, for example `generateImage($prompt, 'photo', '16:9', 'claude')`,
or `AI_GENERATOR_IMAGE_DRIVER` names one.

**Fix.** Pass `openai`, `gemini` or `xai`, or leave the argument out.

## The image is square although I asked for 16:9

**Cause.** With OpenAI, the package only translates `imageAspect` into a size when
`OPENAI_IMAGE_MODEL` contains `dall-e-3`. For every other OpenAI model, including the default
`gpt-image-1`, it asks for `1024x1024`.

**Fix.** Crop the image yourself, or let Gemini or Grok make the image: they get the ratio. See
[the image size](usage.md#image-size).

## Every request also makes an image, and I did not ask for one

**Cause.** `includeImage` is `true` by default.

**Fix.** Pass `includeImage: false` in every `ContentRequest` that only needs text. It saves the
second, billed call to the image provider.

## "Unsupported AI driver: ..." {#unsupported-driver}

**Symptom.** A `RuntimeException` with this message, as soon as something asks for the generator or
the facade.

**Cause.** `AI_GENERATOR_DRIVER`, a name passed to `using()`, or a name in `AI_GENERATOR_IMAGE_DRIVER`
or `generateImage()` is not one the package knows. The package knows `openai`, `anthropic`, `gemini`
and `xai`, and the aliases `chatgpt`, `gpt`, `claude`, `google` and `grok`.

**Fix.**

- Check the spelling. `grok` works, `x.ai` does not.
- You have a driver of your own: check that it is registered with the manager's `extend()` under this
  exact name, or that your binding replaces the one of the package. See
  [Custom drivers](custom-drivers.md).

Then run `php artisan config:clear`.

## I changed .env and nothing happens

**Cause.** One of three:

1. The config is cached: run `php artisan config:clear`, or `php artisan config:cache` again.
2. A queue worker still runs the old code and config: run `php artisan queue:restart`.
3. You published `config/ai-generator.php` and replaced an `env(...)` call with a fixed value. The
   file wins over `.env`.

A published config file from an older version keeps working: no key was renamed or removed. A key
that is missing from your file falls back to the default of the package.

## My fake driver is ignored in a test

**Cause.** `AiGenerator` is a singleton and receives its driver when it is first built. When
something resolved the generator before your test bound the fake, the generator keeps the real
driver.

**Fix.** Bind the fake at the start of the test, before the code under test runs. See
[Testing](testing.md).

## The job fails with a timeout, or runs twice

**Cause.** One `generate()` with an image can take four waits of the provider timeout in the worst
case: two attempts for the text, two for the image. With the default of 45 seconds that is more than
the 60 seconds a queue worker gives a job by default. Every fallback driver adds two more attempts.

**Fix.** Give the job a `$timeout` above that worst case and keep the `retry_after` of the queue
connection above the `$timeout`. The example on [Usage](usage.md#queued-job) sets both `$timeout`
and `$tries`.

## Still stuck

Open an [issue](https://github.com/ArvidDeJong/laravel-ai-generator/issues/new/choose) with the
package version, the Laravel version, the provider, the model names and the full message. Leave your
API key out.
