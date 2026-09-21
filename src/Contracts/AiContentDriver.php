<?php

namespace Darvis\LaravelAiGenerator\Contracts;

use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;
use Darvis\LaravelAiGenerator\Drivers\OpenAiDriver;

/**
 * Contract for AI content generation drivers.
 *
 * Implement this interface to create custom AI provider integrations.
 * Each driver is responsible for communicating with its respective AI service
 * and returning structured content results.
 *
 * @example Creating a custom driver:
 * ```php
 * class AnthropicDriver implements AiContentDriver
 * {
 *     public function generate(ContentRequest $request): ContentResult
 *     {
 *         // Call Anthropic API and return ContentResult
 *     }
 * }
 * ```
 *
 * @see OpenAiDriver
 */
interface AiContentDriver
{
    /**
     * Generate AI-powered content based on the provided request.
     *
     * Implementations should handle API communication, error handling,
     * and response parsing to produce a consistent ContentResult.
     *
     * @param  ContentRequest  $request  The content generation parameters
     * @return ContentResult The generated content with all fields populated
     *
     * @throws \RuntimeException If the AI service is unavailable or returns an error
     */
    public function generate(ContentRequest $request): ContentResult;
}
