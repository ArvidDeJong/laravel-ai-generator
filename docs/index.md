---
title: Home
nav_order: 1
description: "darvis/laravel-ai-generator writes structured content for a Laravel app with OpenAI: title, intro, HTML text, SEO fields and an optional image from one request."
permalink: /
---

# Laravel AI Generator

Writing a first draft of a blog post, a product text or a news item is the part of a CMS that an AI
model does well. This package turns that into one call: you describe the topic, it returns the
fields a page needs, already split up and trimmed.

```bash
composer require darvis/laravel-ai-generator
```

```dotenv
OPENAI_API_KEY=your-api-key-here
```

Requires PHP 8.2 or higher, Laravel 11, 12 or 13, and an OpenAI API key.

## Your first article

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
$result->seoTitle;        // at most 60 characters
$result->seoDescription;  // at most 155 characters
```

Leave out `includeImage: false` and it also writes an image prompt and generates the image, which
is a second call to OpenAI.

## What you can steer

- **Language and tone**: any language, with informal, neutral or formal tone, and the right form of
  address for Dutch, German and French.
- **Reading level and length**: simple, general or expert, and a word limit for the main text.
- **Audience, brand, keywords and a call to action**, woven into the text.
- **The image**: photo, illustration, flat or 3D.
- **The provider**: OpenAI out of the box, or your own driver for another model.

## Where to go next

- [Installation](installation.md): requirements, publishing the config and checking the key.
- [Configuration](configuration.md): every option and environment variable.
- [Usage](usage.md): requests, results, images, queued jobs and error handling.
- [Custom drivers](custom-drivers.md): plug in another AI provider.
- [API reference](api-reference.md): every class and method.
- [FAQ](faq.md): the short answers.
