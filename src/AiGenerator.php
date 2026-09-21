<?php

namespace Darvis\LaravelAiGenerator;

use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\Drivers\OpenAiDriver;
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
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
 * @see ContentRequest
 * @see ContentResult
 * @see AiContentDriver
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
     * @return ContentResult The generated content including title, intro, text, and SEO fields
     *
     * @throws \RuntimeException If the AI driver encounters an error
     */
    public function generate(ContentRequest $request): ContentResult
    {
        // Normalize request with defaults
        $merged = new ContentRequest(
            topic: $request->topic,
            language: $request->language ?? AiGeneratorConfig::defaultLanguage(),
            audience: $request->audience,
            tone: $request->tone ?? AiGeneratorConfig::defaultTone(),
            readingLevel: $request->readingLevel ?? AiGeneratorConfig::defaultReadingLevel(),
            keywords: $request->keywords,
            cta: $request->cta,
            brand: $request->brand,
            maxWords: $request->maxWords ?? AiGeneratorConfig::defaultMaxWords(),
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
     * @return ContentResult A new result instance with sanitized content
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
     * @return array{url?: string|null, base64?: string|null, error?: string}
     */
    public function generateImage(string $prompt, string $style = 'photo', string $aspect = '16:9'): array
    {
        $apiKey = AiGeneratorConfig::openAiApiKey();

        if ($apiKey === null) {
            return ['error' => 'OPENAI_API_KEY is not set.'];
        }

        $url = AiGeneratorConfig::openAiBaseUrl().'/images/generations';
        $payload = OpenAiDriver::imagePayload($this->enhanceImagePrompt($prompt, $style), $aspect);

        try {
            $response = Http::timeout(120)
                ->connectTimeout(30)
                ->retry(1, 1000)
                ->withToken($apiKey)
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
