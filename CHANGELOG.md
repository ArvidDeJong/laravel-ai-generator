# Changelog

All notable changes to `laravel-ai-generator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- A Laravel Boost skill, `laravel-ai-generator-development`, next to the guideline: how a generation
  runs and what each failure gives you, saving the image, queueing, custom drivers and testing
  without calling OpenAI.
- A social preview image for the documentation site.

## [1.1.0] - 2026-09-21

### Added
- Laravel 13 support.
- `Darvis\LaravelAiGenerator\Support\AiGeneratorConfig`, the one place that reads the package
  config. Every default is written there once; before, the same defaults (`nl`, `informal`,
  `general`, `900`, `gpt-image-1` and more) were repeated in the service provider, `AiGenerator` and
  the OpenAI driver. A test fails the build on a direct `config('ai-generator.…')` read.
- `generateImage()` is documented and listed on the facade. It existed since 1.0.0 but no page
  mentioned it.
- A documentation site at https://arviddejong.github.io/laravel-ai-generator/ with an FAQ and an
  `llms.txt`, and a Laravel Boost guideline in `resources/boost/`.
- The tooling of the other darvis packages: Larastan level 8, the `test`, `lint`, `format` and
  `analyse` composer scripts, dependabot, issue and pull request templates, a code of conduct and a
  `.gitattributes` that keeps development files out of the dist archive.

### Fixed
- **The custom driver recipe in the docs threw an exception.** It told you to `extend()` the
  package's `AiContentDriver` binding and set `AI_GENERATOR_DRIVER` to your own driver name. The
  package binding is built before any extender runs and throws `Unsupported AI driver` on a name it
  does not know, so the extender was never reached. The docs now replace the binding in your own
  service provider, and a test runs exactly that recipe.
- The image request was built twice, in `AiGenerator::generateImage()` and in the OpenAI driver.
  It is built in one place now; the requests themselves are unchanged.
- Links in `CONTRIBUTING.md`, the README and the docs pointed at `github.com/darvis`, which is not
  where this package lives.

### Changed
- CI calls the shared workflow in `ArvidDeJong/.github`, which adds Laravel 13 and the
  `prefer-lowest` column that checks the version constraints in `composer.json`.
- `config/ai-generator.php` has its keys sorted alphabetically at every level. No key was renamed or
  removed, so a published config file keeps working.
- The composer scripts `test-coverage` and `format-test` are replaced by `lint` and `analyse`, the
  same names as in the other darvis packages. `phpunit.xml.dist` no longer asks for a coverage
  report on every run, which failed without Xdebug or PCOV.
- Security reports go through GitHub private vulnerability reporting, see `SECURITY.md`.
- `pint.json` uses the plain `laravel` preset like the other darvis packages, without the custom
  rules for concatenation and the not operator. The code is reformatted once to match.

## [1.0.0] - 2025-01-26

### Added
- Initial release
- OpenAI driver with GPT-4.1-mini support
- Content generation with structured JSON output (title, intro, text, SEO fields)
- Image generation support with DALL-E and GPT-Image models
- Multi-language support (Dutch, English, German, French, and more)
- Configurable tone of voice (informal, neutral, formal)
- Configurable reading level (simple, general, expert)
- Laravel Facade support
- Driver-based architecture for easy extension
- Full configuration via environment variables
