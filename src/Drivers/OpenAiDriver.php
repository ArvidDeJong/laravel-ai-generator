<?php

namespace Darvis\LaravelAiGenerator\Drivers;

use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI driver for AI content generation.
 *
 * This driver integrates with OpenAI's API to generate structured content
 * using GPT models for text and DALL-E/GPT-Image for images.
 *
 * Features:
 * - Structured JSON output using OpenAI's response format
 * - Multi-language content generation
 * - Optional image generation with configurable styles
 * - Automatic retry on transient failures
 *
 * @see https://platform.openai.com/docs/api-reference
 */
final class OpenAiDriver implements AiContentDriver
{
    /**
     * Generate content using OpenAI's API.
     *
     * This method performs two API calls:
     * 1. Text generation using the configured GPT model
     * 2. Optional image generation using DALL-E or GPT-Image
     *
     * @param  ContentRequest  $request  The content generation parameters
     * @return ContentResult The generated content with all fields
     *
     * @throws RuntimeException If the API key is missing or API calls fail
     */
    public function generate(ContentRequest $request): ContentResult
    {
        $apiKey = AiGeneratorConfig::openAiApiKey();

        if ($apiKey === null) {
            throw new RuntimeException('OPENAI_API_KEY is not set.');
        }

        $schema = $this->jsonSchema();
        $prompt = $this->buildPrompt($request);

        // 1) Generate text output as strict JSON
        $json = $this->callText($apiKey, $prompt, $schema);

        $result = new ContentResult(
            title: (string) ($json['title'] ?? ''),
            intro: (string) ($json['intro'] ?? ''),
            text: (string) ($json['text'] ?? ''),
            seoTitle: (string) ($json['seo_title'] ?? ''),
            seoDescription: (string) ($json['seo_description'] ?? ''),
            imagePrompt: $request->includeImage ? (string) ($json['image_prompt'] ?? '') : null,
        );

        // 2) Optional: generate actual image (URL/base64)
        if ($request->includeImage && ! empty($result->imagePrompt)) {
            $image = $this->callImage($apiKey, $result->imagePrompt, $request->imageAspect ?? '16:9');

            if (! empty($image['error'])) {
                return new ContentResult(
                    title: $result->title,
                    intro: $result->intro,
                    text: $result->text,
                    seoTitle: $result->seoTitle,
                    seoDescription: $result->seoDescription,
                    imagePrompt: $result->imagePrompt,
                    errorMessage: (string) $image['error'],
                );
            }

            return new ContentResult(
                title: $result->title,
                intro: $result->intro,
                text: $result->text,
                seoTitle: $result->seoTitle,
                seoDescription: $result->seoDescription,
                imagePrompt: $result->imagePrompt,
                imageUrl: $image['url'] ?? null,
                imageBase64: $image['b64_json'] ?? null,
            );
        }

        return $result;
    }

    /**
     * Call OpenAI's text generation API.
     *
     * Uses the Responses API with JSON schema enforcement for structured output.
     *
     * @param  string  $apiKey  The OpenAI API key
     * @param  string  $prompt  The generation prompt
     * @param  array<string, mixed>  $schema  JSON schema for response validation
     * @return array<string, mixed> Decoded JSON response
     *
     * @throws RuntimeException On connection or request failure
     */
    private function callText(string $apiKey, string $prompt, array $schema): array
    {
        $url = AiGeneratorConfig::openAiBaseUrl().'/responses';

        try {
            $response = Http::timeout(AiGeneratorConfig::openAiTimeout())
                ->retry(2, 250)
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'model' => AiGeneratorConfig::openAiModel(),
                    'input' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'input_text', 'text' => $prompt],
                            ],
                        ],
                    ],
                    'temperature' => AiGeneratorConfig::openAiTemperature(),

                    // Force JSON output
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'content_bundle',
                            'schema' => $schema,
                            'strict' => true,
                        ],
                    ],
                ]);

            $response->throw();

        } catch (ConnectionException $e) {
            throw new RuntimeException('OpenAI connection failed: '.$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new RuntimeException('OpenAI request failed: '.$e->getMessage(), 0, $e);
        }

        $data = $response->json();

        // Responses API: output_text is in output[...].content[...].text
        $text = $this->extractOutputText($data);

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned non-JSON output (unexpected).');
        }

        return $decoded;
    }

    /**
     * Call OpenAI's image generation API.
     *
     * @param  string  $apiKey  The OpenAI API key
     * @param  string  $prompt  The image generation prompt (in English)
     * @param  string  $aspect  Desired aspect ratio (1:1, 4:5, 16:9)
     * @return array{url?: string|null, b64_json?: string|null, error?: string} Image data or error
     */
    private function callImage(string $apiKey, string $prompt, string $aspect): array
    {
        $url = AiGeneratorConfig::openAiBaseUrl().'/images/generations';

        try {
            $response = Http::timeout(AiGeneratorConfig::openAiTimeout())
                ->retry(2, 250)
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->post($url, self::imagePayload($prompt, $aspect));

            $response->throw();

        } catch (RequestException|ConnectionException $e) {
            // Image is nice-to-have: don't fail text if image fails.
            return ['error' => 'OpenAI Image Error: '.$e->getMessage()];
        }

        $data = $response->json();
        $first = $data['data'][0] ?? [];

        return [
            'url' => $first['url'] ?? null,
            'b64_json' => $first['b64_json'] ?? null,
        ];
    }

    /**
     * The request body for OpenAI's image generation API, shared with AiGenerator::generateImage().
     *
     * DALL-E 3 gets a size that follows the aspect ratio and is asked for base64 output; other
     * models get a square image and their own default response format.
     *
     * @internal
     *
     * @return array<string, string>
     */
    public static function imagePayload(string $prompt, string $aspect): array
    {
        $imageModel = AiGeneratorConfig::openAiImageModel();
        $normalized = strtolower($imageModel);

        $size = str_contains($normalized, 'dall-e-3')
            ? match ($aspect) {
                '1:1' => '1024x1024',
                '4:5' => '1024x1792',
                default => '1792x1024',
            }
        : '1024x1024';

        $payload = [
            'model' => $imageModel,
            'prompt' => $prompt,
            'size' => $size,
        ];

        // Only DALL-E models support the response_format parameter
        if (str_contains($normalized, 'dall-e')) {
            $payload['response_format'] = 'b64_json';
        }

        return $payload;
    }

    /**
     * Extract the output text from OpenAI's Responses API format.
     *
     * @param  array<string, mixed>  $data  The API response data
     * @return string The extracted text content
     *
     * @throws RuntimeException If no output_text is found in the response
     */
    private function extractOutputText(array $data): string
    {
        // Get the first text we find.
        $output = $data['output'] ?? [];
        foreach ($output as $item) {
            foreach (($item['content'] ?? []) as $c) {
                if (($c['type'] ?? null) === 'output_text' && isset($c['text'])) {
                    return (string) $c['text'];
                }
            }
        }

        throw new RuntimeException('OpenAI response did not include output_text.');
    }

    /**
     * Build the generation prompt from the content request.
     *
     * Constructs a detailed prompt including all request parameters,
     * language-specific instructions, and output format requirements.
     *
     * @param  ContentRequest  $r  The content request
     * @return string The complete prompt for the AI model
     */
    private function buildPrompt(ContentRequest $r): string
    {
        $keywords = $r->keywords ? implode(', ', $r->keywords) : '';
        $brand = $r->brand ? "Brand/organization: {$r->brand}\n" : '';
        $audience = $r->audience ? "Target audience: {$r->audience}\n" : '';
        $cta = $r->cta ? "CTA: {$r->cta}\n" : '';

        $tone = $r->tone ?? AiGeneratorConfig::defaultTone();
        $reading = $r->readingLevel ?? AiGeneratorConfig::defaultReadingLevel();
        $language = $r->language ?? AiGeneratorConfig::defaultLanguage();

        $maxWords = $r->maxWords ?? AiGeneratorConfig::defaultMaxWords();

        $imagePart = $r->includeImage
            ? "Also create an 'image_prompt' (in English) for a {$r->imageStyle} hero image in aspect {$r->imageAspect}. No text in the image.\n"
            : "No image_prompt needed.\n";

        // Build language-specific prompt
        $languageInstructions = $this->getLanguageInstructions($language, $tone);

        return <<<PROMPT
You are a content writer and SEO specialist.

Write content about the topic:
"{$r->topic}"

Requirements:
- Language: {$language}
- Tone of voice: {$tone} {$languageInstructions}
- Reading level: {$reading}
- Maximum approximately {$maxWords} words for the main text (excluding intro).
{$brand}{$audience}{$cta}
- Naturally incorporate these keywords (if provided): {$keywords}

Output:
- Return ONLY valid JSON according to the schema.
- Fields: title, intro, text, seo_title, seo_description, image_prompt
- intro: 2-4 sentences, engaging (plain text)
- text: Use HTML formatting. Use <h2> for subheadings, <p> for paragraphs, and <ul>/<li> for lists. No <h1> or markdown.
- seo_title: max 60 characters
- seo_description: max 155 characters
{$imagePart}
PROMPT;
    }

    /**
     * Get language-specific tone instructions.
     *
     * Returns appropriate pronoun guidance based on language and tone.
     * For example, Dutch informal uses "je/jij", German formal uses "Sie".
     *
     * @param  string  $language  ISO 639-1 language code
     * @param  string  $tone  The desired tone (informal, neutral, formal)
     * @return string Language-specific instructions or empty string
     */
    private function getLanguageInstructions(string $language, string $tone): string
    {
        return match ($language) {
            'nl' => $tone === 'informal' ? '(use je/jij where appropriate)' : '',
            'de' => $tone === 'informal' ? '(use du where appropriate)' : '(use Sie)',
            'fr' => $tone === 'informal' ? '(use tu where appropriate)' : '(use vous)',
            default => '',
        };
    }

    /**
     * Get the JSON schema for structured output.
     *
     * Defines the expected response structure for OpenAI's JSON mode.
     *
     * @return array<string, mixed> JSON schema definition
     */
    private function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'title' => ['type' => 'string'],
                'intro' => ['type' => 'string'],
                'text' => ['type' => 'string'],
                'seo_title' => ['type' => 'string'],
                'seo_description' => ['type' => 'string'],
                'image_prompt' => ['type' => 'string'],
            ],
            'required' => ['title', 'intro', 'text', 'seo_title', 'seo_description', 'image_prompt'],
        ];
    }
}
