<?php

namespace Darvis\LaravelAiGenerator;

/**
 * Data transfer object for content generation requests.
 *
 * This immutable class encapsulates all parameters needed for AI content generation,
 * including the topic, language settings, tone, and image generation options.
 *
 * @example
 * ```php
 * $request = new ContentRequest(
 *     topic: 'Benefits of sustainable packaging',
 *     language: 'en',
 *     tone: 'formal',
 *     keywords: ['eco-friendly', 'sustainable'],
 * );
 * ```
 */
final class ContentRequest
{
    /**
     * Create a new content request instance.
     *
     * @param  string  $topic  The main topic or subject for content generation (required)
     * @param  string|null  $language  ISO 639-1 language code (e.g., 'nl', 'en', 'de', 'fr')
     * @param  string|null  $audience  Target audience description (e.g., 'business owners', 'developers')
     * @param  string|null  $tone  Writing tone: 'informal', 'neutral', or 'formal'
     * @param  string|null  $readingLevel  Content complexity: 'simple', 'general', or 'expert'
     * @param  array<string>|null  $keywords  SEO keywords to incorporate naturally
     * @param  string|null  $cta  Call-to-action text to include
     * @param  string|null  $brand  Brand or organization name for context
     * @param  int|null  $maxWords  Maximum word count for the main text
     * @param  bool  $includeImage  Whether to generate an image prompt (default: true)
     * @param  string|null  $imageStyle  Image style: 'photo', 'illustration', 'flat', or '3d'
     * @param  string|null  $imageAspect  Image aspect ratio: '1:1', '4:5', '16:9', etc.
     */
    public function __construct(
        public readonly string $topic,
        public readonly ?string $language = null,
        public readonly ?string $audience = null,
        public readonly ?string $tone = null,
        public readonly ?string $readingLevel = null,
        public readonly ?array $keywords = null,
        public readonly ?string $cta = null,
        public readonly ?string $brand = null,
        public readonly ?int $maxWords = null,
        public readonly bool $includeImage = true,
        public readonly ?string $imageStyle = 'photo',
        public readonly ?string $imageAspect = '16:9',
    ) {}
}
