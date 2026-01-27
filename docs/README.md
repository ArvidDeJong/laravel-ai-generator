# Laravel AI Generator Documentation

Welcome to the Laravel AI Generator documentation. This package provides AI-powered content generation for Laravel applications using OpenAI.

## Table of Contents

1. [Installation](installation.md) - Getting started with the package
2. [Configuration](configuration.md) - Configuring the package and API settings
3. [Basic Usage](usage.md) - How to generate content
4. [Custom Drivers](custom-drivers.md) - Creating your own AI provider integrations
5. [API Reference](api-reference.md) - Complete class and method reference

## Quick Overview

Laravel AI Generator allows you to generate structured content including:

- **Title** - Article or blog post title
- **Intro** - 2-4 sentence introduction
- **Text** - Main content with HTML formatting
- **SEO Title** - Optimized title for search engines (max 60 chars)
- **SEO Description** - Meta description (max 155 chars)
- **Image** - Optional AI-generated image

## Minimal Example

```php
use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;

$generator = app(AiGenerator::class);

$result = $generator->generate(new ContentRequest(
    topic: 'The benefits of Laravel',
));

echo $result->title;
echo $result->text;
```

## Requirements

- PHP 8.2 or higher
- Laravel 11.0 or 12.0
- OpenAI API key

## Support

- [GitHub Issues](https://github.com/darvis/laravel-ai-generator/issues)
- Email: info@arvid.nl
