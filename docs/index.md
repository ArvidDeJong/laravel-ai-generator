---
title: "Home"
nav_order: 1
description: "darvis/laravel-ai-generator writes structured content for a Laravel app with OpenAI: title, intro, HTML text, SEO fields and an optional image from one request."
permalink: /
---

# Laravel AI Generator

`darvis/laravel-ai-generator` turns a topic into the fields a web page needs, with one call to OpenAI.
You pass a `ContentRequest`, you get a `ContentResult` with a title, an intro, the main text as HTML,
an SEO title, a meta description and, if you want one, an image.

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
- An OpenAI API key. OpenAI bills every call to your OpenAI account; the package is free.

## Install

```bash
composer require darvis/laravel-ai-generator
```

```env
OPENAI_API_KEY=your-api-key-here
```

Then follow [Check that it works](installation.md#check-that-it-works).

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
generates an image, which is a second, billed call to OpenAI.

## Pages

- [Installation](installation.md): from `composer require` to a first result, and a check that costs nothing.
- [Usage](usage.md): one complete example, every request option, the result, images, errors and queued jobs.
- [Configuration](configuration.md): every config key and environment variable with its default.
- [Custom drivers](custom-drivers.md): let another AI provider write the text.
- [Testing](testing.md): test your own code without calling OpenAI.
- [Troubleshooting](troubleshooting.md): every error message the package produces, with cause and fix.
- [API reference](api-reference.md): every public class, method and exception message.
- [FAQ](faq.md): the short answers.

## Links

- [Source on GitHub](https://github.com/ArvidDeJong/laravel-ai-generator)
- [Package on Packagist](https://packagist.org/packages/darvis/laravel-ai-generator)
