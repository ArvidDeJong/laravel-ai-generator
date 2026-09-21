# Laravel AI Generator

[![Latest version](https://img.shields.io/packagist/v/darvis/laravel-ai-generator.svg)](https://packagist.org/packages/darvis/laravel-ai-generator)
[![Tests](https://github.com/ArvidDeJong/laravel-ai-generator/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/laravel-ai-generator/actions/workflows/tests.yml)
[![PHP version](https://img.shields.io/packagist/dependency-v/darvis/laravel-ai-generator/php.svg)](https://packagist.org/packages/darvis/laravel-ai-generator)
[![License](https://img.shields.io/packagist/l/darvis/laravel-ai-generator.svg)](LICENSE)

`darvis/laravel-ai-generator` writes structured content for a Laravel application with OpenAI. You pass a `ContentRequest` with a topic and get a `ContentResult` with a title, an intro, the main text as HTML, an SEO title, a meta description and optionally an image.

## Features

- One call returns `title`, `intro`, `text` (HTML with `h2`, `p`, `ul` and `li`), `seoTitle` and `seoDescription`.
- Asks the OpenAI Responses API for strict JSON, so you get separate fields instead of one text to parse.
- Optional image through the OpenAI image API, and `generateImage()` for an image without text.
- Language, tone, reading level, audience, keywords, brand, call to action and length per request, with defaults in the config.
- A failing image does not lose the text: the result comes back with `hasError()` true.
- Another AI provider for the text through your own `AiContentDriver`.
- No routes, views or migrations: you call it from your own code and store the result yourself.
- Ships a Laravel Boost guideline and skill, so an AI assistant in your application knows the API.

## Requirements

- PHP 8.2 or higher (Laravel 13 itself needs PHP 8.3)
- Laravel 11, 12 or 13
- An OpenAI API key; OpenAI bills every call to your OpenAI account

## Installation

```bash
composer require darvis/laravel-ai-generator
```

Add your OpenAI API key to `.env`:

```env
OPENAI_API_KEY=your-api-key-here
```

Publishing the config file is optional:

```bash
php artisan vendor:publish --tag=ai-generator-config
```

## Quick start

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
$result->text;            // the article as HTML
$result->seoTitle;        // the model is asked for at most 60 characters
$result->seoDescription;  // the model is asked for at most 155 characters
```

`includeImage` is `true` by default. Leave out `includeImage: false` and the package also generates an image, which is a second, billed call to OpenAI. The `text` field is HTML written by a model: sanitise it before you render it unescaped.

## Documentation

The full documentation lives on the [documentation site](https://arviddejong.github.io/laravel-ai-generator/):

- [Installation](https://arviddejong.github.io/laravel-ai-generator/installation.html): step by step, with a check that costs nothing
- [Usage](https://arviddejong.github.io/laravel-ai-generator/usage.html): a complete example, every request option, images, errors and queued jobs
- [Configuration](https://arviddejong.github.io/laravel-ai-generator/configuration.html): every option and environment variable
- [Custom drivers](https://arviddejong.github.io/laravel-ai-generator/custom-drivers.html): let another AI provider write the text
- [Testing](https://arviddejong.github.io/laravel-ai-generator/testing.html): test your code without calling OpenAI
- [Troubleshooting](https://arviddejong.github.io/laravel-ai-generator/troubleshooting.html): every error message with cause and fix
- [API reference](https://arviddejong.github.io/laravel-ai-generator/api-reference.html): every public class and method
- [FAQ](https://arviddejong.github.io/laravel-ai-generator/faq.html): the short answers

## Laravel Boost

The package ships a [Laravel Boost](https://laravel.com/docs/boost) guideline and skill. Run `php artisan boost:install`, or `php artisan boost:update --discover` in a project that already uses Boost.

## Testing

```bash
composer test      # Pest
composer lint      # Pint, check only; composer format fixes
composer analyse   # Larastan
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md).

## Security

Please report a vulnerability privately, as described in [SECURITY](SECURITY.md), not in the issue tracker.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
