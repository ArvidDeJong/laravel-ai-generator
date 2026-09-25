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
        return self::apiKey(Provider::OpenAi);
    }

    /**
     * Base URL of the OpenAI API, without a trailing slash.
     */
    public static function openAiBaseUrl(): string
    {
        return self::baseUrl(Provider::OpenAi);
    }

    /**
     * Model used for the image.
     */
    public static function openAiImageModel(): string
    {
        return (string) self::imageModel(Provider::OpenAi);
    }

    /**
     * Model used for the text.
     */
    public static function openAiModel(): string
    {
        return self::model(Provider::OpenAi);
    }

    /**
     * Sampling temperature for the text.
     */
    public static function openAiTemperature(): float
    {
        return self::temperature(Provider::OpenAi) ?? 0.7;
    }

    /**
     * Seconds before a call to OpenAI from the driver times out.
     */
    public static function openAiTimeout(): int
    {
        return self::timeout(Provider::OpenAi);
    }

    /**
     * The API key of a provider, or null when it is not set or empty.
     */
    public static function apiKey(Provider $provider): ?string
    {
        $key = config("ai-generator.drivers.{$provider->value}.api_key");

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Base URL of the API of a provider, without a trailing slash.
     */
    public static function baseUrl(Provider $provider): string
    {
        return rtrim((string) config("ai-generator.drivers.{$provider->value}.base_url", $provider->defaultBaseUrl()), '/');
    }

    /**
     * Text model of a provider.
     */
    public static function model(Provider $provider): string
    {
        $model = config("ai-generator.drivers.{$provider->value}.model");

        return is_string($model) && $model !== '' ? $model : $provider->defaultModel();
    }

    /**
     * Image model of a provider, or null for a provider without image generation.
     */
    public static function imageModel(Provider $provider): ?string
    {
        if (! $provider->supportsImages()) {
            return null;
        }

        $model = config("ai-generator.drivers.{$provider->value}.image_model");

        return is_string($model) && $model !== '' ? $model : $provider->defaultImageModel();
    }

    /**
     * Sampling temperature for the text of a provider, or null to send none.
     */
    public static function temperature(Provider $provider): ?float
    {
        $temperature = config("ai-generator.drivers.{$provider->value}.temperature", $provider->defaultTemperature());

        return $temperature === null || $temperature === '' ? null : (float) $temperature;
    }

    /**
     * Seconds before one call to a provider times out.
     */
    public static function timeout(Provider $provider): int
    {
        return (int) config("ai-generator.drivers.{$provider->value}.timeout", 45);
    }

    /**
     * The upper limit of tokens Claude may write; the Messages API requires one.
     */
    public static function anthropicMaxTokens(): int
    {
        return (int) config('ai-generator.drivers.anthropic.max_tokens', 8192);
    }

    /**
     * The value of the anthropic-version header.
     */
    public static function anthropicVersion(): string
    {
        return (string) config('ai-generator.drivers.anthropic.version', '2023-06-01');
    }

    /**
     * The driver that makes images, or null to follow the text driver (OpenAI for Claude).
     */
    public static function imageDriver(): ?string
    {
        $driver = config('ai-generator.image_driver');

        return is_string($driver) && trim($driver) !== '' ? trim($driver) : null;
    }

    /**
     * The drivers tried in order when the text call of the default driver fails.
     *
     * @return list<string>
     */
    public static function fallbacks(): array
    {
        $fallbacks = config('ai-generator.fallbacks', []);

        if (is_string($fallbacks)) {
            $fallbacks = explode(',', $fallbacks);
        }

        if (! is_array($fallbacks)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($name) => trim((string) $name), $fallbacks),
            fn (string $name) => $name !== '',
        ));
    }

    /**
     * Whether the package registers its local MCP server when laravel/mcp is installed.
     */
    public static function mcpEnabled(): bool
    {
        return filter_var(config('ai-generator.mcp.enabled', true), FILTER_VALIDATE_BOOL);
    }

    /**
     * The name under which the local MCP server is registered, for php artisan mcp:start.
     */
    public static function mcpHandle(): string
    {
        return (string) config('ai-generator.mcp.handle', 'ai-generator');
    }
}
