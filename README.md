# Laravel AI Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/darvis/laravel-ai-generator.svg?style=flat-square)](https://packagist.org/packages/darvis/laravel-ai-generator)
[![PHP Version](https://img.shields.io/packagist/php-v/darvis/laravel-ai-generator.svg?style=flat-square)](https://packagist.org/packages/darvis/laravel-ai-generator)
[![License](https://img.shields.io/packagist/l/darvis/laravel-ai-generator.svg?style=flat-square)](https://packagist.org/packages/darvis/laravel-ai-generator)

AI-powered content generation service for Laravel applications. Generate blog posts, articles, news items, and more with SEO optimization and optional image generation.

## Features

- 🤖 **OpenAI Integration** - Uses GPT models for high-quality content
- 📝 **Structured Output** - Title, intro, text, SEO title, SEO description
- 🖼️ **Image Generation** - Optional AI-generated hero images
- 🌍 **Multi-language** - Support for Dutch, English, German, French, and more
- 🎯 **SEO Optimized** - Automatic SEO title and meta description
- ⚙️ **Configurable** - Tone, reading level, max words, and more
- 🔌 **Driver-based** - Easy to extend with new AI providers

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+
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

## Usage

### Basic Usage

```php
use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;

$generator = app(AiGenerator::class);

$result = $generator->generate(new ContentRequest(
    topic: 'Your topic here',
));
```

### Full Options

```php
$result = $generator->generate(new ContentRequest(
    topic: 'Sustainable packaging solutions',
    language: 'nl',                    // nl|en|de|fr...
    audience: 'business owners',       // Target audience
    tone: 'formal',                    // informal|neutral|formal
    readingLevel: 'expert',            // general|expert|simple
    keywords: ['eco-friendly', 'packaging', 'sustainable'],
    cta: 'Request a quote',            // Call to action
    brand: 'EcoPack BV',               // Brand name
    maxWords: 600,                     // Max words for main text
    includeImage: true,                // Generate image prompt
    imageStyle: 'photo',               // photo|illustration|flat|3d
    imageAspect: '16:9',               // 1:1|4:5|16:9
));
```

### Using the Facade

```php
use Darvis\LaravelAiGenerator\Facades\AiGenerator;

$result = AiGenerator::generate(new ContentRequest(
    topic: 'Your topic here',
));
```

### ContentResult Properties

```php
$result->title;           // Generated title
$result->intro;           // 2-4 sentence introduction (plain text)
$result->text;            // Main content with HTML formatting
$result->seoTitle;        // SEO title (max 60 chars)
$result->seoDescription;  // Meta description (max 155 chars)
$result->imagePrompt;     // English prompt for image generation
$result->imageUrl;        // Generated image URL (if available)
$result->imageBase64;     // Generated image as base64 (if available)
$result->errorMessage;    // Error message (if any)

// Helper methods
$result->hasError();      // Check if there was an error
$result->hasImage();      // Check if image was generated
```

## Configuration

```php
// config/ai-generator.php

return [
    'driver' => env('AI_GENERATOR_DRIVER', 'openai'),
    'default_language' => env('AI_GENERATOR_LANGUAGE', 'nl'),
    
    'defaults' => [
        'tone' => env('AI_GENERATOR_TONE', 'informal'),
        'reading_level' => env('AI_GENERATOR_LEVEL', 'general'),
        'max_words' => env('AI_GENERATOR_MAX_WORDS', 900),
    ],
    
    'drivers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
            'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
            'timeout' => env('OPENAI_TIMEOUT', 45),
        ],
    ],
];
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `OPENAI_API_KEY` | - | Your OpenAI API key (required) |
| `OPENAI_BASE_URL` | `https://api.openai.com/v1` | OpenAI API base URL |
| `OPENAI_MODEL` | `gpt-4.1-mini` | Model for text generation |
| `OPENAI_IMAGE_MODEL` | `gpt-image-1` | Model for image generation |
| `OPENAI_TEMPERATURE` | `0.7` | Creativity level (0-1) |
| `OPENAI_TIMEOUT` | `45` | Request timeout in seconds |
| `AI_GENERATOR_LANGUAGE` | `nl` | Default content language |
| `AI_GENERATOR_TONE` | `informal` | Default tone of voice |
| `AI_GENERATOR_LEVEL` | `general` | Default reading level |
| `AI_GENERATOR_MAX_WORDS` | `900` | Default max words |

## Creating Custom Drivers

Implement the `AiContentDriver` interface:

```php
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;

class AnthropicDriver implements AiContentDriver
{
    public function generate(ContentRequest $request): ContentResult
    {
        // Your implementation here
    }
}
```

Register in the service provider or config.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email info@arvid.nl instead of using the issue tracker.

## Credits

- [Arvid de Jong](https://github.com/darvis)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
