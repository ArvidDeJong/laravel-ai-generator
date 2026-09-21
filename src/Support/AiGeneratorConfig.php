<?php

namespace Darvis\LaravelAiGenerator\Support;

/**
 * The one place that reads the package config. Callers ask this class, so a default is written
 * once and a caller cannot quietly disagree with config/ai-generator.php about what it is.
 */
final class AiGeneratorConfig
{
    /**
     * Name of the driver the service provider binds, "openai" out of the box.
     */
    public static function driver(): string
    {
        return (string) config('ai-generator.driver', 'openai');
    }

    /**
     * ISO 639-1 language used when a request does not name one.
     */
    public static function defaultLanguage(): string
    {
        return (string) config('ai-generator.default_language', 'nl');
    }

    /**
     * Word limit for the main text when a request does not set one.
     */
    public static function defaultMaxWords(): int
    {
        return (int) config('ai-generator.defaults.max_words', 900);
    }

    /**
     * Reading level when a request does not set one: general, expert or simple.
     */
    public static function defaultReadingLevel(): string
    {
        return (string) config('ai-generator.defaults.reading_level', 'general');
    }

    /**
     * Tone of voice when a request does not set one: informal, neutral or formal.
     */
    public static function defaultTone(): string
    {
        return (string) config('ai-generator.defaults.tone', 'informal');
    }

    /**
     * The OpenAI API key, or null when it is not set or empty.
     */
    public static function openAiApiKey(): ?string
    {
        $key = config('ai-generator.drivers.openai.api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Base URL of the OpenAI API, without a trailing slash.
     */
    public static function openAiBaseUrl(): string
    {
        return rtrim((string) config('ai-generator.drivers.openai.base_url', 'https://api.openai.com/v1'), '/');
    }

    /**
     * Model used for the image.
     */
    public static function openAiImageModel(): string
    {
        return (string) config('ai-generator.drivers.openai.image_model', 'gpt-image-1');
    }

    /**
     * Model used for the text.
     */
    public static function openAiModel(): string
    {
        return (string) config('ai-generator.drivers.openai.model', 'gpt-4.1-mini');
    }

    /**
     * Sampling temperature for the text.
     */
    public static function openAiTemperature(): float
    {
        return (float) config('ai-generator.drivers.openai.temperature', 0.7);
    }

    /**
     * Seconds before a call to OpenAI from the driver times out.
     */
    public static function openAiTimeout(): int
    {
        return (int) config('ai-generator.drivers.openai.timeout', 45);
    }
}
