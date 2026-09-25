<?php

namespace Darvis\LaravelAiGenerator;

use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Darvis\LaravelAiGenerator\Support\ContentPrompt;
use Darvis\LaravelAiGenerator\Support\Provider;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Main AI content generator service.
 *
 * This class serves as the primary entry point for generating AI-powered content.
 * It coordinates between the configuration, request normalization, and the
 * underlying AI driver to produce structured content output.
 *
 * @example
 * ```php
 * $generator = app(AiGenerator::class);
 * $result = $generator->generate(new ContentRequest(
 *     topic: 'Benefits of Laravel',
 *     language: 'en',
 * ));
 *
 * // Another provider for one call, optionally with another model
 * $result = $generator->using('anthropic')->generate($request);
 * ```
 *
 * @see ContentRequest
 * @see ContentResult
 * @see AiContentDriver
 */
final class AiGenerator
{
    /**
     * Create a new AiGenerator instance.
     *
     * @param  AiContentDriver  $driver  The AI driver implementation to use for content generation
     * @param  AiGeneratorManager|null  $manager  Builds the other drivers for using(), fallbacks and images; the container's when null
     * @param  bool  $useFallbacks  Whether a failed text call moves on to the drivers in the fallback config
     */
    public function __construct(
        private readonly AiContentDriver $driver,
        private readonly ?AiGeneratorManager $manager = null,
        private readonly bool $useFallbacks = true,
    ) {}

    /**
     * A generator that writes with another driver, optionally with another text model.
     *
     * The fallback drivers are not used: when you ask for a provider, a failure is reported
     * instead of being answered by another one.
     *
     * @param  string  $driver  openai, anthropic, gemini, xai, an alias (chatgpt, claude, grok) or a name registered with extend()
     * @param  string|null  $model  A model id of that provider, or null for the configured one
     *
     * @throws RuntimeException When no driver has this name
     */
    public function using(string $driver, ?string $model = null): self
    {
        $manager = $this->manager();

        return new self(
            $model === null ? $manager->driver($driver) : $manager->build($driver, $model),
            $manager,
            useFallbacks: false,
        );
    }

    /**
     * Generate AI-powered content based on the provided request.
     *
     * This method merges the request parameters with configured defaults,
     * delegates to the AI driver for content generation, and sanitizes
     * the output for consistent formatting. When the text call fails and
     * fallback drivers are configured, they are tried in order.
     *
     * @param  ContentRequest  $request  The content generation request with topic and options
     * @return ContentResult The generated content including title, intro, text, and SEO fields
     *
     * @throws RuntimeException If the AI driver encounters an error
     */
    public function generate(ContentRequest $request): ContentResult
    {
        // Normalize request with defaults
        $merged = new ContentRequest(
            topic: $request->topic,
            language: $request->language ?? AiGeneratorConfig::defaultLanguage(),
            audience: $request->audience,
            tone: $request->tone ?? AiGeneratorConfig::defaultTone(),
            readingLevel: $request->readingLevel ?? AiGeneratorConfig::defaultReadingLevel(),
            keywords: $request->keywords,
            cta: $request->cta,
            brand: $request->brand,
            maxWords: $request->maxWords ?? AiGeneratorConfig::defaultMaxWords(),
            includeImage: $request->includeImage,
            imageStyle: $request->imageStyle ?? 'photo',
            imageAspect: $request->imageAspect ?? '16:9',
        );

        try {
            return $this->sanitize($this->driver->generate($merged));
        } catch (RuntimeException $e) {
            $fallbacks = $this->fallbackDrivers();

            if ($fallbacks === []) {
                throw $e;
            }

            $errors = [$this->driverName($this->driver).': '.$e->getMessage()];

            foreach ($fallbacks as $name) {
                try {
                    return $this->sanitize($this->manager()->driver($name)->generate($merged));
                } catch (RuntimeException $fallbackError) {
                    $errors[] = $name.': '.$fallbackError->getMessage();
                }
            }

            throw new RuntimeException('Every AI driver failed. '.implode(' | ', $errors), 0, $e);
        }
    }

    /**
     * Sanitize the content result for consistent output formatting.
     *
     * Trims whitespace from all text fields to ensure clean output.
     *
     * @param  ContentResult  $result  The raw result from the AI driver
     * @return ContentResult A new result instance with sanitized content
     */
    private function sanitize(ContentResult $result): ContentResult
    {
        $title = Str::of($result->title)->trim()->toString();
        $intro = Str::of($result->intro)->trim()->toString();
        $text = Str::of($result->text)->trim()->toString();

        $seoTitle = Str::of($result->seoTitle)->trim()->toString();
        $seoDescription = Str::of($result->seoDescription)->trim()->toString();

        return new ContentResult(
            title: $title,
            intro: $intro,
            text: $text,
            seoTitle: $seoTitle,
            seoDescription: $seoDescription,
            imagePrompt: $result->imagePrompt ? trim($result->imagePrompt) : null,
            imageUrl: $result->imageUrl,
            imageBase64: $result->imageBase64,
            errorMessage: $result->errorMessage,
            driver: $result->driver,
            model: $result->model,
        );
    }

    /**
     * Generate only an image without text content.
     *
     * This is a faster method when you only need an image, as it skips
     * the text generation step entirely. The image comes from the named
     * driver, or else from the configured image driver, or else from the
     * text driver when it can make images and from OpenAI when it cannot.
     *
     * @param  string  $prompt  The image generation prompt (in English preferred)
     * @param  string  $style  Image style: photo, illustration, flat, 3d
     * @param  string  $aspect  Aspect ratio: 1:1, 4:5, 16:9
     * @param  string|null  $driver  openai, gemini or xai (or an alias), or null for the configured image driver
     * @return array{url?: string|null, base64?: string|null, error?: string}
     */
    public function generateImage(string $prompt, string $style = 'photo', string $aspect = '16:9', ?string $driver = null): array
    {
        try {
            $images = $driver === null
                ? $this->manager()->imageDriverFor($this->driver)
                : $this->manager()->imageDriver($driver);
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        }

        if ($images === null) {
            $name = $driver ?? AiGeneratorConfig::imageDriver() ?? 'this driver';
            $label = Provider::fromName($name)?->label() ?? $name;

            return ['error' => "{$label} cannot generate images. Use openai, gemini or xai as image driver."];
        }

        return $images->generateImage(ContentPrompt::styledImagePrompt($prompt, $style), $aspect, timeout: 120, attempts: 1);
    }

    /**
     * The drivers to try after the bound one failed, without the bound one and without a
     * provider whose API key is not set.
     *
     * @return list<string>
     */
    private function fallbackDrivers(): array
    {
        if (! $this->useFallbacks) {
            return [];
        }

        $manager = $this->manager();
        $current = $this->driverName($this->driver);
        $names = [];

        foreach (AiGeneratorConfig::fallbacks() as $name) {
            $name = $manager->normalize($name);
            $provider = Provider::fromName($name);

            if ($name === $current || in_array($name, $names, true)) {
                continue;
            }

            if ($provider !== null && AiGeneratorConfig::apiKey($provider) === null) {
                continue;
            }

            $names[] = $name;
        }

        return $names;
    }

    private function driverName(AiContentDriver $driver): string
    {
        return $driver instanceof Drivers\Driver
            ? $driver->provider()->value
            : $this->manager()->normalize(AiGeneratorConfig::driver());
    }

    private function manager(): AiGeneratorManager
    {
        return $this->manager ?? app(AiGeneratorManager::class);
    }
}
