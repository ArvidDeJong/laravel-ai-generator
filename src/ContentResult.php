<?php

namespace Darvis\LaravelAiGenerator;

/**
 * Data transfer object containing the generated content result.
 *
 * This immutable class holds all output from the AI content generation,
 * including the main content fields, SEO metadata, and optional image data.
 *
 * @example
 * ```php
 * $result = $generator->generate($request);
 *
 * if ($result->hasError()) {
 *     Log::error($result->errorMessage);
 * } else {
 *     echo $result->title;
 *     echo $result->text;
 * }
 * ```
 */
final class ContentResult
{
    /**
     * Create a new content result instance.
     *
     * @param  string  $title  The generated article/content title
     * @param  string  $intro  A 2-4 sentence introduction (plain text)
     * @param  string  $text  The main content body with HTML formatting
     * @param  string  $seoTitle  SEO-optimized title (max 60 characters)
     * @param  string  $seoDescription  Meta description for SEO (max 155 characters)
     * @param  string|null  $imagePrompt  English prompt for image generation
     * @param  string|null  $imageUrl  URL to the generated image (if available)
     * @param  string|null  $imageBase64  Base64-encoded image data (if available)
     * @param  string|null  $errorMessage  Error message if generation partially failed
     */
    public function __construct(
        public readonly string $title,
        public readonly string $intro,
        public readonly string $text,
        public readonly string $seoTitle,
        public readonly string $seoDescription,
        public readonly ?string $imagePrompt = null,
        public readonly ?string $imageUrl = null,
        public readonly ?string $imageBase64 = null,
        public readonly ?string $errorMessage = null,
    ) {}

    /**
     * Check if the content generation encountered an error.
     *
     * Note: An error may be present even with valid content if, for example,
     * the text generation succeeded but image generation failed.
     *
     * @return bool  True if an error message is present
     */
    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }

    /**
     * Check if the result includes generated image data.
     *
     * Returns true if either a URL or base64-encoded image is available.
     *
     * @return bool  True if image data is present
     */
    public function hasImage(): bool
    {
        return $this->imageUrl !== null || $this->imageBase64 !== null;
    }
}
