<?php

/*
|--------------------------------------------------------------------------
| AI Generator
|--------------------------------------------------------------------------
|
| Keys are sorted alphabetically within every group, so a setting is found
| by name instead of by history. The package reads them through
| Darvis\LaravelAiGenerator\Support\AiGeneratorConfig.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    |
    | The default language for generated content when not specified in the
    | request. Uses ISO 639-1 language codes.
    |
    */

    'default_language' => env('AI_GENERATOR_LANGUAGE', 'nl'),

    /*
    |--------------------------------------------------------------------------
    | Content Defaults
    |--------------------------------------------------------------------------
    |
    | Default values for content generation when not specified in the request.
    |
    */

    'defaults' => [
        'max_words' => env('AI_GENERATOR_MAX_WORDS', 900),
        'reading_level' => env('AI_GENERATOR_LEVEL', 'general'),  // general|expert|simple
        'tone' => env('AI_GENERATOR_TONE', 'informal'),           // informal|neutral|formal
    ],

    /*
    |--------------------------------------------------------------------------
    | Default AI Driver
    |--------------------------------------------------------------------------
    |
    | The driver that writes the text. You may set this to any of the drivers
    | defined below; chatgpt, claude and grok work as aliases. Run
    | "php artisan ai-generator:install" to set up one or more providers.
    |
    | Supported: "openai", "anthropic", "gemini", "xai"
    |
    */

    'driver' => env('AI_GENERATOR_DRIVER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Drivers Configuration
    |--------------------------------------------------------------------------
    |
    | The settings of every provider. Only the providers you use need an API
    | key. A temperature of null sends none, which the newest Claude models
    | require; OpenAI reasoning models reject a temperature as well, so set
    | OPENAI_TEMPERATURE to an empty value for those.
    |
    */

    'drivers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 8192),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
            'temperature' => env('ANTHROPIC_TEMPERATURE'),
            'timeout' => env('ANTHROPIC_TIMEOUT', 45),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image'),
            'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
            'temperature' => env('GEMINI_TEMPERATURE'),
            'timeout' => env('GEMINI_TIMEOUT', 45),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
            'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
            'timeout' => env('OPENAI_TIMEOUT', 45),
        ],
        'xai' => [
            'api_key' => env('XAI_API_KEY'),
            'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
            'image_model' => env('XAI_IMAGE_MODEL', 'grok-imagine-image-2.0'),
            'model' => env('XAI_MODEL', 'grok-4.7'),
            'temperature' => env('XAI_TEMPERATURE'),
            'timeout' => env('XAI_TIMEOUT', 45),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Drivers
    |--------------------------------------------------------------------------
    |
    | Drivers to try in order when the text call of the default driver fails,
    | as a comma separated list such as "anthropic,openai". A driver without
    | an API key is skipped. AiGenerator::using() never falls back.
    |
    */

    'fallbacks' => env('AI_GENERATOR_FALLBACKS', ''),

    /*
    |--------------------------------------------------------------------------
    | Image Driver
    |--------------------------------------------------------------------------
    |
    | The driver that makes images: "openai", "gemini" or "xai". Null follows
    | the text driver, and uses OpenAI when the text driver cannot make images
    | (Claude has no image model).
    |
    */

    'image_driver' => env('AI_GENERATOR_IMAGE_DRIVER'),

    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    |
    | With laravel/mcp installed, the package registers a local MCP server so
    | an assistant such as Claude Code can generate content through this app:
    | "php artisan mcp:start ai-generator". It runs on your machine only; a
    | web server is up to you, behind your own authentication.
    |
    */

    'mcp' => [
        'enabled' => env('AI_GENERATOR_MCP', true),
        'handle' => env('AI_GENERATOR_MCP_HANDLE', 'ai-generator'),
    ],

];
