<?php

namespace Darvis\LaravelAiGenerator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the AI Generator service.
 *
 * Provides static access to the AiGenerator instance for convenient usage.
 *
 * @method static \Darvis\LaravelAiGenerator\ContentResult generate(\Darvis\LaravelAiGenerator\ContentRequest $request) Generate AI content from a request
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
