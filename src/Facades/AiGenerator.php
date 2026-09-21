<?php

namespace Darvis\LaravelAiGenerator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the AI Generator service.
 *
 * Provides static access to the AiGenerator instance for convenient usage.
 *
 * @method static \Darvis\LaravelAiGenerator\ContentResult generate(\Darvis\LaravelAiGenerator\ContentRequest $request) Generate AI content from a request
 * @method static array{url?: string|null, base64?: string|null, error?: string} generateImage(string $prompt, string $style = 'photo', string $aspect = '16:9') Generate only an image
 *
 * @example
 * ```php
 * use Darvis\LaravelAiGenerator\Facades\AiGenerator;
 * use Darvis\LaravelAiGenerator\ContentRequest;
 *
 * $result = AiGenerator::generate(new ContentRequest(
 *     topic: 'Laravel best practices',
 *     language: 'en',
 * ));
 * ```
 *
 * @see \Darvis\LaravelAiGenerator\AiGenerator
 */
class AiGenerator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai-generator';
    }
}
