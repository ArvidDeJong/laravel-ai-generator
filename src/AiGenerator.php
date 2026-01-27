<?php

namespace Darvis\LaravelAiGenerator;

use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Main AI content generator service.
 *
 * This class serves as the primary entry point for generating AI-powered content.
 * It coordinates between the configuration, request normalization, and the
 * underlying AI driver to produce structured content output.
 *
 * @example
 * ```php
 * $generator = app(AiGenerator::class);
 * $result = $generator->generate(new ContentRequest(
 *     topic: 'Benefits of Laravel',
 *     language: 'en',
 * ));
 * ```
 *
 * @see \Darvis\LaravelAiGenerator\ContentRequest
 * @see \Darvis\LaravelAiGenerator\ContentResult
 * @see \Darvis\LaravelAiGenerator\Contracts\AiContentDriver
 */
final class AiGenerator
{
    /**
     * Create a new AiGenerator instance.
     *
     * @param  AiContentDriver  $driver  The AI driver implementation to use for content generation
     */
    public function __construct(
        private readonly AiContentDriver $driver,
    ) {}

    /**
     * Generate AI-powered content based on the provided request.
     *
     * This method merges the request parameters with configured defaults,
     * delegates to the AI driver for content generation, and sanitizes
     * the output for consistent formatting.
     *
     * @param  ContentRequest  $request  The content generation request with topic and options
     * @return ContentResult  The generated content including title, intro, text, and SEO fields
     *
     * @throws \RuntimeException  If the AI driver encounters an error
     */
    public function generate(ContentRequest $request): ContentResult
    {
        $defaults = Config::get('ai-generator.defaults', []);

        // Normalize request with defaults
        $merged = new ContentRequest(
            topic: $request->topic,
            language: $request->language ?? Config::get('ai-generator.default_language', 'nl'),
            audience: $request->audience,
            tone: $request->tone ?? Arr::get($defaults, 'tone', 'informal'),
            readingLevel: $request->readingLevel ?? Arr::get($defaults, 'reading_level', 'general'),
            keywords: $request->keywords,
            cta: $request->cta,
            brand: $request->brand,
            maxWords: $request->maxWords ?? (int) Arr::get($defaults, 'max_words', 900),
            includeImage: $request->includeImage,
            imageStyle: $request->imageStyle ?? 'photo',
            imageAspect: $request->imageAspect ?? '16:9',
        );

        $result = $this->driver->generate($merged);

        return $this->sanitize($result);
    }

    /**
     * Sanitize the content result for consistent output formatting.
     *
     * Trims whitespace from all text fields to ensure clean output.
     *
     * @param  ContentResult  $result  The raw result from the AI driver
     * @return ContentResult  A new result instance with sanitized content
     */
    private function sanitize(ContentResult $result): ContentResult
    {
        $title = Str::of($result->title)->trim()->toString();
        $intro = Str::of($result->intro)->trim()->toString();
        $text = Str::of($result->text)->trim()->toString();

        $seoTitle = Str::of($result->seoTitle)->trim()->toString();
        $seoDescription = Str::of($result->seoDescription)->trim()->toString();

        return new ContentResult(
            title: $title,
            intro: $intro,
            text: $text,
            seoTitle: $seoTitle,
            seoDescription: $seoDescription,
            imagePrompt: $result->imagePrompt ? trim($result->imagePrompt) : null,
            imageUrl: $result->imageUrl,
            imageBase64: $result->imageBase64,
            errorMessage: $result->errorMessage,
        );
    }

    /**
     * Generate only an image without text content.
     *
     * This is a faster method when you only need an image, as it skips
     * the text generation step entirely.
     *
     * @param  string  $prompt  The image generation prompt (in English preferred)
     * @param  string  $style  Image style: photo, illustration, flat, 3d
     * @param  string  $aspect  Aspect ratio: 1:1, 4:5, 16:9
     * @return array{url?: string, base64?: string, error?: string}
     */
    public function generateImage(string $prompt, string $style = 'photo', string $aspect = '16:9'): array
    {
        $cfg = Config::get('ai-generator.drivers.openai');

        if (empty($cfg['api_key'])) {
            return ['error' => 'OPENAI_API_KEY is not set.'];
        }

        $imageModel = strtolower((string) ($cfg['image_model'] ?? 'gpt-image-1'));

        // Map aspect ratio to size
        if (str_contains($imageModel, 'dall-e-3')) {
            $size = match ($aspect) {
                '1:1' => '1024x1024',
                '4:5' => '1024x1792',
                '16:9', '4:3' => '1792x1024',
                default => '1792x1024',
            };
        } else {
            $size = '1024x1024';
        }

        $baseUrl = rtrim((string) $cfg['base_url'], '/');
        $url = $baseUrl.'/images/generations';

        // Enhance prompt with style
        $enhancedPrompt = $this->enhanceImagePrompt($prompt, $style);

        $payload = [
            'model' => (string) ($cfg['image_model'] ?? 'gpt-image-1'),
            'prompt' => $enhancedPrompt,
            'size' => $size,
        ];

        // Only DALL-E models support response_format parameter
        if (str_contains($imageModel, 'dall-e')) {
            $payload['response_format'] = 'b64_json';
        }

        try {
            $response = Http::timeout(120)
                ->connectTimeout(30)
                ->retry(1, 1000)
                ->withToken((string) $cfg['api_key'])
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            $response->throw();

            $data = $response->json();
            $first = $data['data'][0] ?? [];

            return [
                'url' => $first['url'] ?? null,
                'base64' => $first['b64_json'] ?? null,
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Enhance the image prompt with style instructions.
     */
    private function enhanceImagePrompt(string $prompt, string $style): string
    {
        $stylePrefix = match ($style) {
            'photo' => 'Professional high-quality photograph of',
            'illustration' => 'Digital illustration of',
            'flat' => 'Flat design vector illustration of',
            '3d' => '3D rendered image of',
            default => '',
        };

        return $stylePrefix ? "{$stylePrefix} {$prompt}. No text or watermarks." : $prompt;
    }
}
