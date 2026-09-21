# Laravel AI Generator

[![Latest version](https://img.shields.io/packagist/v/darvis/laravel-ai-generator.svg)](https://packagist.org/packages/darvis/laravel-ai-generator)
[![Tests](https://github.com/ArvidDeJong/laravel-ai-generator/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/laravel-ai-generator/actions/workflows/tests.yml)
[![PHP version](https://img.shields.io/packagist/dependency-v/darvis/laravel-ai-generator/php.svg)](https://packagist.org/packages/darvis/laravel-ai-generator)
[![License](https://img.shields.io/packagist/l/darvis/laravel-ai-generator.svg)](LICENSE)

AI-powered content generation service for Laravel applications. Generate blog posts, articles, news items, and more with SEO optimization and optional image generation.

## Features

- 🤖 **OpenAI Integration** - Uses GPT models for high-quality content
- 📝 **Structured Output** - Title, intro, text, SEO title, SEO description
- 🖼️ **Image Generation** - Optional AI-generated hero images
- 🌍 **Multi-language** - Support for Dutch, English, German, French, and more
- 🎯 **SEO Optimized** - Automatic SEO title and meta description
- ⚙️ **Configurable** - Tone, reading level, max words, and more
- 🔌 **Driver-based** - Easy to extend with new AI providers
- 🤖 **Laravel Boost** - Guideline included, so an AI assistant in your app knows the API

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13
- OpenAI API key

## Installation

```bash
composer require darvis/laravel-ai-generator
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=ai-generator-config
```

Add your OpenAI API key to `.env`:

```env
OPENAI_API_KEY=your-api-key-here
```

## Quick Start

```php
use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;

$generator = app(AiGenerator::class);

$result = $generator->generate(new ContentRequest(
    topic: 'The benefits of Laravel for web development',
    language: 'en',
));

// Access the generated content
echo $result->title;           // "Why Laravel is the Best PHP Framework"
echo $result->intro;           // "Laravel has revolutionized..."
echo $result->text;            // "<h2>Introduction</h2><p>..."
echo $result->seoTitle;        // "Laravel Benefits | Web Development"
echo $result->seoDescription;  // "Discover why Laravel..."
```

## Documentation

The full documentation lives on the [documentation site](https://arviddejong.github.io/laravel-ai-generator/):

- [Installation](https://arviddejong.github.io/laravel-ai-generator/installation.html)
- [Configuration](https://arviddejong.github.io/laravel-ai-generator/configuration.html): every option and environment variable
- [Usage](https://arviddejong.github.io/laravel-ai-generator/usage.html): requests, results, images, queued jobs and error handling
- [Custom drivers](https://arviddejong.github.io/laravel-ai-generator/custom-drivers.html): plug in another AI provider
- [API reference](https://arviddejong.github.io/laravel-ai-generator/api-reference.html)

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
