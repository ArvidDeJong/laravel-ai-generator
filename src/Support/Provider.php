<?php

namespace Darvis\LaravelAiGenerator\Support;

/**
 * The AI providers the package ships a driver for, with the facts the drivers, the install wizard
 * and the MCP server share. The value is the driver name in config/ai-generator.php.
 */
enum Provider: string
{
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case OpenAi = 'openai';
    case Xai = 'xai';

    /**
     * The provider for a driver name or one of its aliases (chatgpt, claude, google, grok).
     */
    public static function fromName(string $name): ?self
    {
        return match (strtolower(trim($name))) {
            'anthropic', 'claude' => self::Anthropic,
            'gemini', 'google' => self::Gemini,
            'openai', 'chatgpt', 'gpt' => self::OpenAi,
            'xai', 'grok' => self::Xai,
            default => null,
        };
    }

    /**
     * The name people know the provider by.
     */
    public function label(): string
    {
        return match ($this) {
            self::Anthropic => 'Claude (Anthropic)',
            self::Gemini => 'Gemini (Google)',
            self::OpenAi => 'ChatGPT (OpenAI)',
            self::Xai => 'Grok (xAI)',
        };
    }

    /**
     * The short name used in error messages, such as "Claude request failed: ...".
     */
    public function shortName(): string
    {
        return match ($this) {
            self::Anthropic => 'Claude',
            self::Gemini => 'Gemini',
            self::OpenAi => 'OpenAI',
            self::Xai => 'Grok',
        };
    }

    /**
     * The prefix of the environment variables of this provider, such as ANTHROPIC for ANTHROPIC_MODEL.
     */
    public function envPrefix(): string
    {
        return match ($this) {
            self::Anthropic => 'ANTHROPIC',
            self::Gemini => 'GEMINI',
            self::OpenAi => 'OPENAI',
            self::Xai => 'XAI',
        };
    }

    public function apiKeyEnv(): string
    {
        return $this->envPrefix().'_API_KEY';
    }

    /**
     * Where an account holder creates an API key.
     */
    public function apiKeyUrl(): string
    {
        return match ($this) {
            self::Anthropic => 'https://platform.claude.com/settings/keys',
            self::Gemini => 'https://aistudio.google.com/apikey',
            self::OpenAi => 'https://platform.openai.com/api-keys',
            self::Xai => 'https://console.x.ai/team/default/api-keys',
        };
    }

    public function defaultBaseUrl(): string
    {
        return match ($this) {
            self::Anthropic => 'https://api.anthropic.com/v1',
            self::Gemini => 'https://generativelanguage.googleapis.com/v1beta',
            self::OpenAi => 'https://api.openai.com/v1',
            self::Xai => 'https://api.x.ai/v1',
        };
    }

    public function defaultModel(): string
    {
        return match ($this) {
            self::Anthropic => 'claude-sonnet-5',
            self::Gemini => 'gemini-3.8-flash',
            self::OpenAi => 'gpt-4.1-mini',
            self::Xai => 'grok-4.7',
        };
    }

    /**
     * The default image model, or null for a provider without image generation.
     */
    public function defaultImageModel(): ?string
    {
        return match ($this) {
            self::Anthropic => null,
            self::Gemini => 'gemini-3.1-flash-image',
            self::OpenAi => 'gpt-image-1',
            self::Xai => 'grok-imagine-image-2.0',
        };
    }

    /**
     * The sampling temperature sent when none is configured. Null sends none: the newest Claude
     * models reject a temperature, and Gemini and Grok have good defaults of their own.
     */
    public function defaultTemperature(): ?float
    {
        return $this === self::OpenAi ? 0.7 : null;
    }

    public function supportsImages(): bool
    {
        return $this->defaultImageModel() !== null;
    }
}
