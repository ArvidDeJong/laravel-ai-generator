<?php

namespace Darvis\LaravelAiGenerator\Drivers;

use Darvis\LaravelAiGenerator\Contracts\AiImageDriver;
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Darvis\LaravelAiGenerator\Support\Provider;
use RuntimeException;

/**
 * OpenAI driver for AI content generation.
 *
 * This driver integrates with OpenAI's API to generate structured content
 * using GPT models for text and GPT-Image for images.
 *
 * Features:
 * - Structured JSON output using the Responses API with a strict JSON schema
 * - Multi-language content generation
 * - Optional image generation with configurable styles
 * - Automatic retry on transient failures
 *
 * @see https://platform.openai.com/docs/api-reference
 */
final class OpenAiDriver extends Driver implements AiImageDriver
{
    public function provider(): Provider
    {
        return Provider::OpenAi;
    }

    /**
     * Call OpenAI's Responses API with JSON schema enforcement.
     *
     * @param  array<string, mixed>  $schema
     *
     * @throws RuntimeException On connection or request failure
     */
    protected function requestJson(string $apiKey, string $prompt, array $schema): string
    {
        $response = $this->post($this->http()->withToken($apiKey), $this->url('responses'), [
            'model' => $this->model(),
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $prompt],
                    ],
                ],
            ],
            ...$this->temperature(),

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

        // Responses API: output_text is in output[...].content[...].text
        foreach ((array) $response->json('output', []) as $item) {
            foreach (($item['content'] ?? []) as $c) {
                if (($c['type'] ?? null) === 'output_text' && isset($c['text'])) {
                    return (string) $c['text'];
                }
            }
        }

        throw new RuntimeException('OpenAI response did not include output_text.');
    }

    /**
     * Generate an image with OpenAI's image API.
     *
     * @return array{url?: string|null, base64?: string|null, error?: string}
     */
    public function generateImage(string $prompt, string $aspect = '16:9', ?int $timeout = null, int $attempts = 2): array
    {
        $apiKey = AiGeneratorConfig::apiKey(Provider::OpenAi);

        if ($apiKey === null) {
            return ['error' => 'OPENAI_API_KEY is not set.'];
        }

        $response = $this->postImage(
            $this->http($timeout, $attempts)->withToken($apiKey),
            $this->url('images/generations'),
            self::imagePayload($prompt, $aspect),
        );

        if (is_string($response)) {
            return ['error' => $response];
        }

        $first = $response->json('data.0') ?? [];

        return [
            'url' => $first['url'] ?? null,
            'base64' => $first['b64_json'] ?? null,
        ];
    }

    /**
     * The request body for OpenAI's image generation API.
     *
     * GPT Image models and DALL-E 3 get a size that follows the aspect ratio; other models get a
     * square image. Only DALL-E models are asked for base64 output: GPT Image models always return
     * base64 and do not support response_format.
     *
     * @internal
     *
     * @return array<string, string>
     */
    public static function imagePayload(string $prompt, string $aspect): array
    {
        $imageModel = AiGeneratorConfig::openAiImageModel();
        $normalized = strtolower($imageModel);

        $size = match (true) {
            str_contains($normalized, 'gpt-image') => match ($aspect) {
                '16:9' => '1536x1024',
                '4:5' => '1024x1536',
                default => '1024x1024',
            },
            str_contains($normalized, 'dall-e-3') => match ($aspect) {
                '1:1' => '1024x1024',
                '4:5' => '1024x1792',
                default => '1792x1024',
            },
            default => '1024x1024',
        };

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
}
