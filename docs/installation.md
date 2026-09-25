---
title: "Installation"
nav_order: 2
description: "Install darvis/laravel-ai-generator step by step: requirements, the setup wizard for Claude, ChatGPT, Gemini and Grok, and a check that it works without spending money."
---

# Installation

## Requirements

- PHP 8.2 or higher. Laravel 13 itself needs PHP 8.3.
- Laravel 11, 12 or 13.
- An API key of at least one provider: Claude (Anthropic), ChatGPT (OpenAI), Gemini (Google) or
  Grok (xAI). Don't have one yet? [Get your API keys](api-keys.md) explains where, step by step.
  The provider bills every call to your account; the package itself is free.

The package has no migrations, routes or views, so there is nothing to migrate or to link.

## Step 1: install the package

Run this in the root folder of your Laravel application:

```bash
composer require darvis/laravel-ai-generator
```

Laravel discovers the service provider by itself. You don't register anything.

## Step 2: run the setup wizard {#the-setup-wizard}

```bash
php artisan ai-generator:install
```

The wizard asks you a few questions and writes the answers to your `.env` file. It asks:

1. **Which providers you have a key for.** Use the arrow keys and the space bar to select one or
   more, then press Enter.
2. **The API key of each provider.** Paste it; you won't see it on the screen. When a key is already
   set, the wizard asks whether to keep it.
3. The wizard then **checks the key** with the provider. It only asks for the list of models, which
   costs nothing. When the provider refuses the key, you can enter another one, keep it anyway or
   skip that provider.
4. **Which model** writes the text, and for OpenAI, Gemini and Grok which model makes the images.
   You choose from the models your key may use. Not sure? Keep the one marked "(current)".
5. With more than one provider: **which one writes by default**, which one **makes the images**, and
   which ones **take over when the first one fails**. See [Multiple providers](providers.md).
6. Optionally the **default language, tone and reading level**.
7. A summary, with the keys partly hidden, and whether to write it to `.env`.

`.env` is the file with the settings and secrets of your application. It must never end up in git;
[Keep your keys secret](api-keys.md#keep-your-keys-secret) explains why and how.

Run the wizard again at any time to add a provider or change a model.

### Or set the key by hand

Prefer to edit `.env` yourself? Add the key of your provider, for example:

```env
OPENAI_API_KEY=your-api-key-here
```

With another provider than OpenAI, also say which driver writes the text:

```env
ANTHROPIC_API_KEY=your-api-key-here
AI_GENERATOR_DRIVER=anthropic
```

Every other setting has a default; see [Configuration](configuration.md).

## Step 3 (optional): publish the config file

You only need this when you want to change a setting in PHP instead of in `.env`.

```bash
php artisan vendor:publish --tag=ai-generator-config
```

This copies the file to `config/ai-generator.php` in your application.

## Check that it works {#check-that-it-works}

Do this in two steps. The first one costs nothing.

### 1. The keys work

```bash
php artisan ai-generator:status
```

You see a table with the four providers. For every provider with a key, the column **Check** says
`OK` when the provider accepted the key. This only asks each provider for its list of models, which
is free. Below the table you see the default driver, the image driver, the fallbacks and whether the
MCP server is available.

- `no key`: that provider has no key. That is fine when you don't use it.
- `... refused the API key (HTTP 401).`: the key is wrong or revoked. See
  [Troubleshooting](troubleshooting.md#key-refused).
- `key OK, but ... is not in the model list`: your key works, but the configured model is not one
  your key may use. Pick another one with the wizard.

Add `--offline` to see the settings without contacting any provider.

### 2. One real call, without an image

This sends one text request to the driver you name, and is billed to your account. It never makes an
image.

```bash
php artisan ai-generator:status --test=anthropic
```

Use `openai`, `anthropic`, `gemini` or `xai`. After some seconds you see the model, the title and the
intro it wrote. The words differ on every run.

The same check in PHP, for example in `php artisan tinker`:

```php
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;

AiGenerator::generate(new ContentRequest(
    topic: 'Why automated tests matter',
    language: 'en',
    maxWords: 100,
    includeImage: false,
))->title;
```

`includeImage: false` keeps it to one call and `maxWords: 100` keeps the text short. When you see an
exception instead, its message tells you what went wrong. Every message is listed in
[Troubleshooting](troubleshooting.md).

## Laravel Boost

The package ships a [Laravel Boost](https://laravel.com/docs/boost) guideline and a skill in
`resources/boost/`. Run `php artisan boost:install`, or `php artisan boost:update --discover` in a
project that already uses Boost. Your AI assistant then knows the API, the defaults and the pitfalls:
the image that is generated by default, the providers, the custom driver binding and how to test
without calling a real API.

## Next steps

- [Usage](usage.md): a complete example and every request option.
- [Multiple providers](providers.md): Claude for the text, OpenAI for the images, and fallbacks.
- [MCP server](mcp.md): let an assistant such as Claude Code generate content through your app.
- [Configuration](configuration.md): change a model, the default language or the timeout.
