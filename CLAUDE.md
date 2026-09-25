# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. The conventions shared by every darvis package (language, releases, CI, docs site, Boost guidelines, public API policy) are in [../CLAUDE.md](../CLAUDE.md); this file only holds what is specific to this package.

## Package overview

`darvis/laravel-ai-generator` is a Laravel package (PHP 8.2+, Laravel 11/12/13) that turns a `ContentRequest` into a `ContentResult` with a title, intro, HTML text, SEO fields and an optional image, using OpenAI, Claude (Anthropic), Gemini or Grok (xAI).

- Namespace: `Darvis\LaravelAiGenerator\` → `src/`
- Service provider auto-registered via `extra.laravel.providers` in [composer.json](composer.json)
- Config key: `ai-generator`

## Architecture

- [AiGeneratorConfig](src/Support/AiGeneratorConfig.php) is the only place that reads the package config; don't call `config('ai-generator.…')` elsewhere. `tests/Feature/ConfigAccessorTest.php` walks `src/`, `resources/` and `routes/` for it. Defaults per provider live in the [Provider](src/Support/Provider.php) enum and in the config file; the test checks they agree.
- [AiGeneratorManager](src/AiGeneratorManager.php) builds drivers by name (aliases via `Provider::fromName()`), caches them, takes `extend()` creators and decides the image driver: configured `image_driver`, else the text driver when it implements `AiImageDriver`, else OpenAI.
- The service provider binds `AiContentDriver` to `manager->driver()` and throws `Unsupported AI driver: …` on an unknown name. A host app may still replace the binding; `$app->extend()` on the binding never gets there. `tests/Feature/CustomDriverTest.php` pins both.
- [AiGenerator](src/AiGenerator.php) fills the empty request fields, calls the bound driver, runs the fallback drivers on a `RuntimeException` (only for the default generator, never after `using()`) and trims the result. `generateImage()` uses `imageDriverFor($boundDriver)`, so an app with the `openai` driver or a custom driver still gets OpenAI images, as since 1.0.0.
- The built-in drivers extend the `@internal` [Driver](src/Drivers/Driver.php) base: key check, HTTP settings, error messages and the image step. A driver only implements `requestJson()`. The prompt, schema and parsing are shared in [ContentPrompt](src/Support/ContentPrompt.php), so every provider gets the same instruction.
- `src/Mcp` extends `laravel/mcp`, which is only a `suggest`: it needs Laravel 12.41+, and the CI matrix installs Laravel 11. PHPStan excludes `src/Mcp` and `tests/Feature/McpServerTest.php` skips without it. Before changing `src/Mcp`, run `composer require --dev laravel/mcp`, test and analyse with it, and remove it again (`composer remove --dev laravel/mcp`) before committing.
- The install wizard writes `.env` through [EnvFile](src/Support/EnvFile.php) and checks keys with [ModelCatalog](src/Support/ModelCatalog.php), which only calls the free model-list endpoints.

## Conventions

- Keep the public API compatible within 1.x: `generate()`, `using()`, `generateImage()` and its array shape (`url`, `base64`, `error`), the `AiContentDriver` and `AiImageDriver` contracts, `AiGeneratorManager::extend()`, and the constructor arguments and properties of `ContentRequest` and `ContentResult`. Adding a method to `AiContentDriver` breaks every custom driver; image support is a separate contract for that reason. New `ContentResult` properties go at the end, with a default.
- `includeImage` defaults to `true`. Changing that default is a behaviour change for every host app; don't do it within 1.x.
- Never call a real AI API from a test.
- Never send a temperature to Claude by default: Opus 5.5, Opus 5 and Sonnet 5 answer HTTP 400 on one. The same goes for OpenAI reasoning models, which is why an empty `OPENAI_TEMPERATURE` sends none.
- Never make the MCP server public from the package: it only registers `Mcp::local()`. A web server spends the app's credits, so it is the host app's choice, behind its own auth.
- Model ids age fast. The defaults are in `Provider` and the config file; the wizard lists the models the key may use, so a stale default is a nuisance, not a dead end. The OpenAI image default is `gpt-image-2` since 1.3.0, because `gpt-image-1` shuts down on 2026-10-23 (not 2026-12-01, as 1.2.0 wrote).
