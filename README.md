# Laravel AI Generator

[![Latest version](https://img.shields.io/packagist/v/darvis/laravel-ai-generator.svg)](https://packagist.org/packages/darvis/laravel-ai-generator)
[![Tests](https://github.com/ArvidDeJong/laravel-ai-generator/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/laravel-ai-generator/actions/workflows/tests.yml)
[![PHP version](https://img.shields.io/packagist/dependency-v/darvis/laravel-ai-generator/php.svg)](https://packagist.org/packages/darvis/laravel-ai-generator)
[![License](https://img.shields.io/packagist/l/darvis/laravel-ai-generator.svg)](LICENSE)

`darvis/laravel-ai-generator` writes structured content for a Laravel application with **Claude, ChatGPT, Gemini or Grok**. You pass a `ContentRequest` with a topic and get a `ContentResult` with a title, an intro, the main text as HTML, an SEO title, a meta description and optionally an image.

## Features

- One call returns `title`, `intro`, `text` (HTML with `h2`, `p`, `ul` and `li`), `seoTitle` and `seoDescription`.
- Four providers out of the box: OpenAI (ChatGPT), Anthropic (Claude), Google (Gemini) and xAI (Grok). Every one is asked for strict JSON, so you get separate fields instead of one text to parse.
- Use several at once: one provider writes, another makes the images (Claude has no image model), and others take over when the first one fails. Pick another provider or model per call with `AiGenerator::using('anthropic')`.
- A setup wizard, `php artisan ai-generator:install`, that checks every API key with the provider, lets you pick the models your key may use and writes `.env` for you. `php artisan ai-generator:status` checks the keys again at no cost.
- An MCP server (with `laravel/mcp`), so an assistant such as Claude Code can generate content and images through your application.
- Language, tone, reading level, audience, keywords, brand, call to action and length per request, with defaults in the config.
- A failing image does not lose the text: the result comes back with `hasError()` true.
- No routes, views or migrations: you call it from your own code and store the result yourself.
- Ships a Laravel Boost guideline and skill, so an AI assistant in your application knows the API.

## Requirements

- PHP 8.2 or higher (Laravel 13 itself needs PHP 8.3)
- Laravel 11, 12 or 13
- An API key of at least one provider. A ChatGPT, Claude or Gemini subscription is not an API key: the API is billed separately, per call. The [API keys guide](https://arviddejong.github.io/laravel-ai-generator/api-keys.html) shows step by step where to create one.
- Optional: `laravel/mcp` (Laravel 12.41 or newer) for the MCP server

## Installation

```bash
composer require darvis/laravel-ai-generator
php artisan ai-generator:install
```

The wizard asks which providers you have a key for, checks each key, and writes the settings to `.env`. You can also set them by hand:

```env
ANTHROPIC_API_KEY=your-claude-key
OPENAI_API_KEY=your-openai-key

AI_GENERATOR_DRIVER=anthropic        # Claude writes the text
AI_GENERATOR_IMAGE_DRIVER=openai     # OpenAI makes the images
AI_GENERATOR_FALLBACKS=openai        # OpenAI writes when Claude fails
```

Never commit `.env` or put a key in your code. Publishing the config file is optional:

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

Another provider or model for one call:

```php
$result = AiGenerator::using('claude')->generate($request);
$result = AiGenerator::using('openai', 'gpt-4.1-mini')->generate($request);
```

`includeImage` is `true` by default. Leave out `includeImage: false` and the package also generates an image, which is a second, billed call. The `text` field is HTML written by a model: sanitise it before you render it unescaped.

## Documentation

The full documentation lives on the [documentation site](https://arviddejong.github.io/laravel-ai-generator/):

- [API keys](https://arviddejong.github.io/laravel-ai-generator/api-keys.html): where to create a key for each provider, and how to keep it secret
- [Installation](https://arviddejong.github.io/laravel-ai-generator/installation.html): the wizard step by step, with a check that costs nothing
- [Multiple providers](https://arviddejong.github.io/laravel-ai-generator/providers.html): default, image and fallback drivers, and `using()`
- [MCP server](https://arviddejong.github.io/laravel-ai-generator/mcp.html): let an assistant generate content through your app
- [Usage](https://arviddejong.github.io/laravel-ai-generator/usage.html): a complete example, every request option, images, errors and queued jobs
- [Configuration](https://arviddejong.github.io/laravel-ai-generator/configuration.html): every option and environment variable
- [Custom drivers](https://arviddejong.github.io/laravel-ai-generator/custom-drivers.html): let another AI provider write the text
- [Testing](https://arviddejong.github.io/laravel-ai-generator/testing.html): test your code without calling a real API
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

## Support the package

If darvis/laravel-ai-generator saves you time, a star on [GitHub](https://github.com/ArvidDeJong/laravel-ai-generator) or a favourite on [Packagist](https://packagist.org/packages/darvis/laravel-ai-generator) helps other developers find it.

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md).

## Security

Please report a vulnerability privately, as described in [SECURITY](SECURITY.md), not in the issue tracker.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
