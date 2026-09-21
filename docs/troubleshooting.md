---
title: "Troubleshooting"
nav_order: 7
description: "Every error message of darvis/laravel-ai-generator with its cause and fix: missing API key, failed requests, timeouts, no image, unknown driver, stale config."
---

# Troubleshooting

Each section is a symptom, then the cause, then the fix. The messages are quoted literally from the
package, so you can search this page for the text you see.

Start with the check that costs nothing: [Check that it works](installation.md#check-that-it-works).

## "OPENAI_API_KEY is not set." {#api-key-not-set}

**Symptom.** `generate()` throws a `RuntimeException` with this message, or `generateImage()` returns
`['error' => 'OPENAI_API_KEY is not set.']`. No request was sent.

**Cause.** The config value `ai-generator.drivers.openai.api_key` is empty. Usually one of these:

1. `OPENAI_API_KEY` is missing from `.env`, or it is in `.env.example` instead of `.env`.
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
`'api_key' => env('OPENAI_API_KEY')`.

## "OpenAI request failed: HTTP request returned status code ..."

**Symptom.** `generate()` throws a `RuntimeException` that starts with `OpenAI request failed:`,
followed by Laravel's message `HTTP request returned status code` with the status and the start of
OpenAI's answer.

**Cause.** OpenAI answered with a 4xx or 5xx status, on both attempts. The answer of OpenAI in the
message says why. The status tells you where to look:

| Status | Look at |
| --- | --- |
| 401 (unauthorized) | The API key in `OPENAI_API_KEY`. |
| 400 or 404 (bad request, not found) | What the package sent: `OPENAI_MODEL`, `OPENAI_TEMPERATURE` and `OPENAI_BASE_URL`. The package always sends a `temperature` and a strict JSON schema. |
| 429 (too many requests) | The limits of your OpenAI account. |
| 5xx (server error) | The other side. Try again later. |

**Fix.** Read the rest of the message, correct the setting it points at, and run
`php artisan config:clear` when your config is cached.

## "OpenAI connection failed: ..."

**Symptom.** `generate()` throws a `RuntimeException` that starts with `OpenAI connection failed:`.

**Cause.** No answer arrived within `OPENAI_TIMEOUT` seconds (45 by default), on both attempts, or the
host could not be reached at all.

**Fix.**

- Raise the timeout in `.env`, for example `OPENAI_TIMEOUT=90`.
- Check `OPENAI_BASE_URL` when you changed it.
- Check that the server may make outgoing HTTPS requests.
- Generate in a [queued job](usage.md#queued-job), so a slow answer does not block a visitor.

## "OpenAI returned non-JSON output (unexpected)." or "OpenAI response did not include output_text."

**Symptom.** `generate()` throws a `RuntimeException` with one of these two messages.

**Cause.** The request succeeded, but the answer did not have the shape of the OpenAI Responses API,
or the text in it was not the JSON object the package asked for.

**Fix.** Check `OPENAI_BASE_URL` and `OPENAI_MODEL`: the endpoint and the model have to support the
Responses API with a JSON schema. In a test, check the shape of your fake on [Testing](testing.md).

## The text is there, but there is no image

**Symptom.** `hasImage()` is `false`.

**Cause and fix.** Look at `errorMessage` and `imagePrompt`:

| What you see | Cause | Fix |
| --- | --- | --- |
| `errorMessage` starts with `OpenAI Image Error:` | The image request failed on both attempts. The rest of the message is the HTTP status or the timeout. | A status: check `OPENAI_IMAGE_MODEL` and whether your OpenAI account may use it. A timeout: raise `OPENAI_TIMEOUT`, which also counts for the image inside `generate()`. |
| `imagePrompt` is `null` | The request had `includeImage: false`. | Pass `includeImage: true` or leave the argument out. |
| `imagePrompt` is an empty string and there is no error | The model wrote no image prompt, so the package sent no image request. | Generate again, or call `generateImage()` with a prompt of your own. |
| No image, no error, and you use a custom driver | The package does not make the image for a custom driver. | Let your driver do it, or call `generateImage()`; see [Custom drivers](custom-drivers.md). |

A failed image never throws. The text is complete, so you can save it and try the image again with
`generateImage($result->imagePrompt)`.

## The image is square although I asked for 16:9

**Cause.** The package only translates `imageAspect` into a size when `OPENAI_IMAGE_MODEL` contains
`dall-e-3`. For every other model, including the default `gpt-image-1`, it asks for `1024x1024`.

**Fix.** Crop the image yourself, or use a `dall-e-3` model. See [the image size](usage.md#image-size).

## Every request also makes an image, and I did not ask for one

**Cause.** `includeImage` is `true` by default.

**Fix.** Pass `includeImage: false` in every `ContentRequest` that only needs text. It saves the
second, billed call to OpenAI.

## "Unsupported AI driver: ..." {#unsupported-driver}

**Symptom.** A `RuntimeException` with this message, as soon as something asks for the generator or
the facade.

**Cause.** `AI_GENERATOR_DRIVER` holds a name other than `openai`, and no binding of your own replaced
the one of the package.

**Fix.**

- You don't have a driver of your own: remove `AI_GENERATOR_DRIVER` from `.env` or set it to `openai`.
- You do: check that your service provider is registered, that it binds `AiContentDriver` in
  `register()`, and that the name in its `if` equals `AI_GENERATOR_DRIVER`. Don't use `extend()`. See
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

**Cause.** One `generate()` with an image can take four waits of `OPENAI_TIMEOUT` seconds in the
worst case: two attempts for the text, two for the image. With the default of 45 seconds that is
more than the 60 seconds a queue worker gives a job by default.

**Fix.** Give the job a `$timeout` above that worst case and keep the `retry_after` of the queue
connection above the `$timeout`. The example on [Usage](usage.md#queued-job) sets both `$timeout`
and `$tries`.

## Still stuck

Open an [issue](https://github.com/ArvidDeJong/laravel-ai-generator/issues/new/choose) with the
package version, the Laravel version, the model names and the full message. Leave your API key out.
