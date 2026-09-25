# Changelog

All notable changes to `laravel-ai-generator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **The default OpenAI image model is now `gpt-image-2`** instead of `gpt-image-1`. OpenAI shuts
  `gpt-image-1` down on 2026-10-23 and names `gpt-image-2` as its replacement. Is
  `OPENAI_IMAGE_MODEL=gpt-image-1` set in your `.env`? Change it to `gpt-image-2`, or remove the
  line; after 2026-10-23 OpenAI refuses the old model. `gpt-image-2` may need the same OpenAI
  organization verification as `gpt-image-1`.
- **GPT Image models now follow `imageAspect`.** `16:9` asks for `1536x1024` and `4:5` for
  `1024x1536`; `1:1` stays `1024x1024`. Before, every GPT Image model got a square image. Crop the
  image when your layout needs an exact 16:9 or 4:5.

### Fixed
- The documentation of 1.2.0 said `gpt-image-1` shuts down on 2026-12-01. The date is 2026-10-23.

## [1.2.0] - 2026-09-25

### Added
- **Claude, Gemini and Grok.** Three new drivers next to OpenAI: `anthropic` (Messages API with
  structured outputs), `gemini` (`generateContent` with a JSON schema) and `xai` (chat completions
  with a strict JSON schema). The aliases `chatgpt`, `claude`, `google` and `grok` work too. Set
  `ANTHROPIC_API_KEY`, `GEMINI_API_KEY` or `XAI_API_KEY` and `AI_GENERATOR_DRIVER`.
- **Several providers at once.** `AiGenerator::using('anthropic')` or `using('openai', 'model-id')`
  writes one call with another provider or model. `AI_GENERATOR_IMAGE_DRIVER` picks the provider
  for images (Claude has no image model, so with Claude as text driver the image comes from OpenAI
  unless you set it). `AI_GENERATOR_FALLBACKS=anthropic,openai` lists providers that take over when
  the text call of the default driver fails.
- `generateImage()` takes an optional fourth argument, the driver that makes the image.
- `ContentResult` has two new properties, `driver` and `model`, that say who wrote the text.
- **Setup wizard:** `php artisan ai-generator:install` asks which providers you have a key for,
  checks every key with the provider, lets you pick the text and image model from the list your
  key may use, asks for the default, image and fallback drivers and writes `.env`.
- `php artisan ai-generator:status` shows every provider and checks the keys at no cost;
  `--test=claude` generates one short, billed text.
- **MCP server:** with `laravel/mcp` (Laravel 12.41 or newer) installed, the package registers the
  local MCP server `ai-generator` with the tools `list-providers`, `generate-content` and
  `generate-image`. `AI_GENERATOR_MCP=false` turns it off.
- `AiGeneratorManager` builds the drivers by name; register a driver of your own with
  `extend('name', fn ($app, ?string $model) => new YourDriver)`.
- The `AiImageDriver` contract for drivers that make images. `AiContentDriver` is unchanged, so a
  custom driver keeps working.
- Documentation: a beginners' guide to creating API keys for every provider and keeping them
  secret, and pages about multiple providers and the MCP server.

### Changed
- The package now requires `illuminate/console` and `laravel/prompts` for the wizard. Both come
  with every Laravel application.
- An empty `OPENAI_TEMPERATURE` now sends no temperature instead of `0`, for OpenAI models that
  reject one.
- `generateImage()` now uses the image driver instead of always OpenAI. For an application with
  the `openai` driver or a custom driver nothing changes: that image driver is OpenAI.

## [1.1.2] - 2026-09-21

### Fixed
- **Documentation, custom drivers:** the example driver read its settings from
  `config('ai-generator.drivers.anthropic...')` and hard coded a model name and an API version of a
  third party. A driver of your own keeps its settings in your application, for example
  `config/services.php`; the page now shows a made up provider with placeholders, and says that the
  package makes no image for a custom driver.
- **Documentation, retries:** the Boost skill said a text call fails "after 2 retries". The OpenAI
  driver makes two attempts in total, 250 milliseconds apart, for the text and for the image inside
  `generate()`. `generateImage()` makes one attempt.
- **Documentation, timeouts:** `OPENAI_TIMEOUT` was described as "request timeout". It counts per
  attempt, for the text and for the image inside `generate()`; `generateImage()` ignores it and uses
  a fixed 120 seconds.
- **Documentation, Azure OpenAI:** the configuration page and the Boost skill said that pointing
  `OPENAI_BASE_URL` at an Azure deployment works. That was never tested. The package appends
  `/responses` and `/images/generations` and sends the key as a Bearer token; the pages now say only
  that.
- **Documentation, custom driver name:** the Boost skill said not to set `AI_GENERATOR_DRIVER` to
  your own name, while the docs and a test do exactly that. It works as long as your binding replaces
  the one of the package.
- **Documentation, verify step:** the installation page checked the installation with a request that
  also generated an image. The check now starts with a step that does not call OpenAI, followed by
  one text request with `includeImage: false`.
- `AI_GENERATOR_DRIVER` was missing from the table of environment variables.
- Claims the package cannot guarantee are gone or reworded: that an image URL expires, which field
  `gpt-image-1` fills, how many seconds a call takes, and the SEO field lengths, which are an
  instruction to the model and not a limit the package enforces.
- Two checks in `tests/DocsSiteTest.php` always passed: a message passed as second argument to a
  negated `toContain()` counts as a second needle. They now fail on `{{ }}` outside a raw block and
  on a relative link out of `docs/`.
- The facade has no global alias; the usage page now says to import it, and that the facade and the
  service class share the name `AiGenerator`.

### Added
- Documentation: a [testing](https://arviddejong.github.io/laravel-ai-generator/testing.html) page
  with a fake driver, `Http::fake()` in the shape of the Responses API and a test for a custom driver.
- Documentation: a [troubleshooting](https://arviddejong.github.io/laravel-ai-generator/troubleshooting.html)
  page with every message the package produces, its cause and its fix.
- The usage page starts with one complete, runnable example (an Artisan command), and the API
  reference lists the `AiGeneratorConfig` accessors and every exception message.
- Two guards in `tests/DocsSiteTest.php`: links between pages resolve and every page is linked from
  the home page, and the first examples show `includeImage: false`.

## [1.1.1] - 2026-09-21

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
