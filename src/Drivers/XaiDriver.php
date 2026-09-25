<?php

namespace Darvis\LaravelAiGenerator\Drivers;

use Darvis\LaravelAiGenerator\Contracts\AiImageDriver;
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Darvis\LaravelAiGenerator\Support\Provider;
use RuntimeException;

/**
 * Grok driver, through the xAI chat completions API with a strict JSON schema for the text and the
 * xAI image API for the image.
 *
 * @see https://docs.x.ai/docs/guides/structured-outputs
 */
final class XaiDriver extends Driver implements AiImageDriver
{
    public function provider(): Provider
    {
        return Provider::Xai;
    }

    /**
     * @param  array<string, mixed>  $schema
     *
     * @throws RuntimeException On connection or request failure, or an answer without text
     */
    protected function requestJson(string $apiKey, string $prompt, array $schema): string
    {
        $response = $this->post($this->http()->withToken($apiKey), $this->url('chat/completions'), [
            'model' => $this->model(),
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            ...$this->temperature(),
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'content_bundle',
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
        ]);

        if (is_string($refusal = $response->json('choices.0.message.refusal')) && $refusal !== '') {
            throw new RuntimeException("Grok declined to write about this topic: {$refusal}");
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('Grok response did not include message content.');
        }

        return $content;
    }

    /**
     * Generate an image with the xAI image API.
     *
     * @return array{url?: string|null, base64?: string|null, error?: string}
     */
    public function generateImage(string $prompt, string $aspect = '16:9', ?int $timeout = null, int $attempts = 2): array
    {
        $apiKey = AiGeneratorConfig::apiKey(Provider::Xai);

        if ($apiKey === null) {
            return ['error' => 'XAI_API_KEY is not set.'];
        }

        $response = $this->postImage($this->http($timeout, $attempts)->withToken($apiKey), $this->url('images/generations'), [
            'model' => AiGeneratorConfig::imageModel(Provider::Xai),
            'prompt' => $prompt,
            'n' => 1,
            'response_format' => 'b64_json',
            // xAI has no 4:5; 3:4 is the nearest portrait ratio it offers.
            'aspect_ratio' => $aspect === '4:5' ? '3:4' : $aspect,
        ]);

        if (is_string($response)) {
            return ['error' => $response];
        }

        $first = $response->json('data.0') ?? [];

        return [
            'url' => $first['url'] ?? null,
            'base64' => $first['b64_json'] ?? null,
        ];
    }
}
