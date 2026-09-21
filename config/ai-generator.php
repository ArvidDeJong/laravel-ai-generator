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
    | This option controls the default AI driver that will be used to generate
    | content. You may set this to any of the drivers defined below.
    |
    | Supported: "openai"
    |
    */

    'driver' => env('AI_GENERATOR_DRIVER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Drivers Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for each AI driver. Currently only
    | OpenAI is supported, but the architecture allows for easy extension.
    |
    */

    'drivers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
            'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
            'timeout' => env('OPENAI_TIMEOUT', 45),
        ],
    ],

];
