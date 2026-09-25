---
title: "Home"
nav_order: 1
description: "darvis/laravel-ai-generator writes structured content for a Laravel app with Claude, ChatGPT, Gemini or Grok: title, intro, HTML text, SEO fields and an optional image."
permalink: /
---

# Laravel AI Generator

`darvis/laravel-ai-generator` turns a topic into the fields a web page needs, with one call to an AI
provider: Claude (Anthropic), ChatGPT (OpenAI), Gemini (Google) or Grok (xAI). You pass a
`ContentRequest`, you get a `ContentResult` with a title, an intro, the main text as HTML, an SEO
title, a meta description and, if you want one, an image.

Use one provider, or several: for example Claude for the text, OpenAI for the images, and OpenAI
again when Claude is down. A setup wizard checks your keys and writes the settings, and an optional
MCP server lets an assistant such as Claude Code generate content through your app.

It is for Laravel developers who build a CMS, a blog or a web shop and want a first draft of a text
without writing prompts and parsing API responses themselves.

## What it does not do

- It has no routes, views, migrations or screens. You call it from your own code.
- It does not save anything. You store the result, and the image, yourself.
- It does not sanitise the generated HTML. See [Usage](usage.md#sanitise-html).
- It does not queue anything by itself. See [Usage](usage.md#queued-job).

## Requirements

- PHP 8.2 or higher (Laravel 13 itself needs PHP 8.3)
- Laravel 11, 12 or 13
- An API key of at least one provider: Claude, ChatGPT, Gemini or Grok.
  [Get your API keys](api-keys.md) shows where, step by step. The provider bills every call to your
  account; the package is free.

## Install

```bash
composer require darvis/laravel-ai-generator
php artisan ai-generator:install
```

The wizard asks which providers you use, checks each key for free and writes `.env`. Then follow
[Check that it works](installation.md#check-that-it-works).

## In short

```php
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;

$result = AiGenerator::generate(new ContentRequest(
    topic: 'The benefits of Laravel for web development',
    language: 'en',
    includeImage: false,
));

$result->title;           // the headline
$result->intro;           // two to four sentences, plain text
$result->text;            // the article as HTML: h2, p, ul and li
$result->seoTitle;        // the model is asked for at most 60 characters
$result->seoDescription;  // the model is asked for at most 155 characters
```

**`includeImage` is `true` by default.** Leave out `includeImage: false` and the package also
generates an image, which is a second, billed call to the provider that makes images.

Another provider for one call:

```php
$result = AiGenerator::using('anthropic')->generate(new ContentRequest(
    topic: 'The benefits of Laravel for web development',
    includeImage: false,
));
```

## Pages

- [Installation](installation.md): from `composer require` and the setup wizard to a first result, and a check that costs nothing.
- [Get your API keys](api-keys.md): where to create a key for each provider, what it costs and how to keep it secret.
- [Usage](usage.md): one complete example, every request option, the result, images, errors and queued jobs.
- [Multiple providers](providers.md): Claude, ChatGPT, Gemini and Grok side by side, images and fallbacks.
- [Configuration](configuration.md): every config key and environment variable with its default.
- [MCP server](mcp.md): let an assistant such as Claude Code generate content through your app.
- [Custom drivers](custom-drivers.md): add a provider the package does not ship.
- [Testing](testing.md): test your own code without calling a real AI API.
- [Troubleshooting](troubleshooting.md): every error message the package produces, with cause and fix.
- [API reference](api-reference.md): every public class, method and exception message.
- [FAQ](faq.md): the short answers.

## Links

- [Source on GitHub](https://github.com/ArvidDeJong/laravel-ai-generator)
- [Package on Packagist](https://packagist.org/packages/darvis/laravel-ai-generator)
