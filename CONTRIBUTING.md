# Contributing

Contributions are **welcome** and will be fully **credited**.

## Pull Requests

We accept contributions via Pull Requests on [GitHub](https://github.com/darvis/laravel-ai-generator).

### Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run the tests (`composer test`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Guidelines

- **[PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)** - The easiest way to apply the conventions is to use [Laravel Pint](https://laravel.com/docs/pint).

- **Add tests!** - Your patch won't be accepted if it doesn't have tests.

- **Document any change in behaviour** - Make sure the `README.md` and any other relevant documentation are kept up-to-date.

- **Consider our release cycle** - We try to follow [SemVer v2.0.0](https://semver.org/). Randomly breaking public APIs is not an option.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please [squash them](https://www.git-scm.com/book/en/v2/Git-Tools-Rewriting-History#_squashing) before submitting.

## Running Tests

```bash
composer test
```

## Running Tests with Coverage

```bash
composer test-coverage
```

## Code Style

We use [Laravel Pint](https://laravel.com/docs/pint) for code styling. You can run it with:

```bash
./vendor/bin/pint
```

## Creating a New Driver

If you want to add support for a new AI provider:

1. Create a new class in `src/Drivers/` that implements `AiContentDriver`
2. Add the driver configuration to `config/ai-generator.php`
3. Register the driver in `AiGeneratorServiceProvider`
4. Add tests for the new driver
5. Update the documentation

Example:

```php
<?php

namespace Darvis\LaravelAiGenerator\Drivers;

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

## Reporting Issues

If you discover any security-related issues, please email info@arvid.nl instead of using the issue tracker.

For general bugs and feature requests, please use the [GitHub issue tracker](https://github.com/darvis/laravel-ai-generator/issues).

**Happy coding!**
