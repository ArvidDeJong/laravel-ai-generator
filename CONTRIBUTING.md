# Contributing

Contributions are welcome: bug reports, fixes, documentation and ideas.

## Before you start

- **Bugs:** open an [issue](https://github.com/ArvidDeJong/laravel-ai-generator/issues/new/choose) with the steps to reproduce. Leave your API key out.
- **Features:** open an issue first. This package stays small on purpose, so let's agree a feature fits before you build it. A new AI provider usually fits better as your own driver; see [Custom drivers](https://arviddejong.github.io/laravel-ai-generator/custom-drivers.html).
- **Security issues:** don't open an issue; see [SECURITY.md](SECURITY.md).

## Development

```bash
git clone https://github.com/ArvidDeJong/laravel-ai-generator.git
cd laravel-ai-generator
composer install

composer test      # Pest
composer lint      # Pint, check only (composer format fixes)
composer analyse   # Larastan, level 8
```

CI runs the tests on PHP 8.2 to 8.4 with Laravel 11, 12 and 13, on the lowest and the latest dependencies.

## Pull requests

- Add or update tests for every change in behaviour. Never call a real AI API from a test; use `Http::fake()` or a fake driver.
- Keep the public API compatible within 1.x: `AiGenerator::generate()`, `AiGenerator::generateImage()`, the `AiContentDriver` contract and the properties of `ContentRequest` and `ContentResult`.
- Read settings through `Support\AiGeneratorConfig`, never with `config('ai-generator.…')`.
- Write code, comments and messages in English.
- Update `docs/`, `CHANGELOG.md` (under `Unreleased`) and `resources/boost/` when users will notice the change.
- The documentation in `docs/` is also the website. Don't write `{{ }}` or `{% %}` there; Jekyll would render it.

## Code of conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md).
