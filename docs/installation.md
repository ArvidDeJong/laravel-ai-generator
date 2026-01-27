# Installation

## Requirements

Before installing, ensure your environment meets these requirements:

- PHP 8.2 or higher
- Laravel 11.0 or 12.0
- An OpenAI API key

## Install via Composer

```bash
composer require darvis/laravel-ai-generator
```

The package uses Laravel's auto-discovery, so the service provider will be registered automatically.

## Publish Configuration

Publish the configuration file to customize settings:

```bash
php artisan vendor:publish --tag=ai-generator-config
```

This creates `config/ai-generator.php` in your application.

## Set Your API Key

Add your OpenAI API key to your `.env` file:

```env
OPENAI_API_KEY=sk-your-api-key-here
```

> **Security Note**: Never commit your API key to version control. Always use environment variables.

## Verify Installation

Test that everything works:

```php
use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;

$generator = app(AiGenerator::class);

$result = $generator->generate(new ContentRequest(
    topic: 'Test content generation',
    language: 'en',
));

dd($result);
```

## Troubleshooting

### "OPENAI_API_KEY is not set"

Ensure your `.env` file contains the `OPENAI_API_KEY` variable and that you've cleared the config cache:

```bash
php artisan config:clear
```

### Connection Timeout

If requests are timing out, increase the timeout in your `.env`:

```env
OPENAI_TIMEOUT=60
```

### Rate Limiting

If you're hitting OpenAI's rate limits, consider implementing queued jobs for content generation.

## Next Steps

- [Configuration](configuration.md) - Customize default settings
- [Basic Usage](usage.md) - Learn how to generate content
