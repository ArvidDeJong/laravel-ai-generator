# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. The conventions shared by every darvis package (language, releases, CI, docs site, Boost guidelines, public API policy) are in [../CLAUDE.md](../CLAUDE.md); this file only holds what is specific to this package.

## Package overview

`darvis/laravel-ai-generator` is a Laravel package (PHP 8.2+, Laravel 11/12/13) that turns a `ContentRequest` into a `ContentResult` with a title, intro, HTML text, SEO fields and an optional image, using OpenAI.

- Namespace: `Darvis\LaravelAiGenerator\` → `src/`
- Service provider auto-registered via `extra.laravel.providers` in [composer.json](composer.json)
- Config key: `ai-generator`

## Architecture

- [AiGeneratorConfig](src/Support/AiGeneratorConfig.php) is the only place that reads the package config; don't call `config('ai-generator.…')` elsewhere. `tests/Feature/ConfigAccessorTest.php` walks `src/`, `resources/` and `routes/` for it.
- [AiGenerator](src/AiGenerator.php) fills the empty request fields from the config, calls the bound `AiContentDriver` and trims the result. `generateImage()` always calls the OpenAI image API, whatever driver is bound; that is documented behaviour since 1.0.0.
- [OpenAiDriver](src/Drivers/OpenAiDriver.php) uses the Responses API with a strict JSON schema for the text, then optionally the image API. `imagePayload()` builds the image request for both paths; it is `@internal`.
- The service provider binds `AiContentDriver` with a `match` on the driver name and throws on an unknown one. A host app with its own driver replaces the binding; `extend()` never gets there. `tests/Feature/CustomDriverTest.php` pins both.

## Conventions

- Keep the public API compatible within 1.x: `generate()`, `generateImage()` and its array shape (`url`, `base64`, `error`), the `AiContentDriver` contract, and the constructor arguments and properties of `ContentRequest` and `ContentResult`. Adding a method to `AiContentDriver` breaks every custom driver.
- `includeImage` defaults to `true`. Changing that default is a behaviour change for every host app; don't do it within 1.x.
- Never call a real AI API from a test.
