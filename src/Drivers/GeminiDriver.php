<?php

namespace Darvis\LaravelAiGenerator\Drivers;

use Darvis\LaravelAiGenerator\Contracts\AiImageDriver;
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Darvis\LaravelAiGenerator\Support\Provider;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * Gemini driver, through generateContent with a JSON schema for the text and a Gemini image model
 * for the image.
 *
 * @see https://ai.google.dev/api/generate-content
 */
final class GeminiDriver extends Driver implements AiImageDriver
{
    public function provider(): Provider
    {
        return Provider::Gemini;
    }

    /**
     * @param  array<string, mixed>  $schema
     *
     * @throws RuntimeException On connection or request failure, or an answer without text
     */
    protected function requestJson(string $apiKey, string $prompt, array $schema): string
    {
        $response = $this->post($this->authenticated($this->http(), $apiKey), $this->generateUrl($this->model()), [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => $schema,
                ...$this->temperature(),
            ],
        ]);

        if (is_string($blocked = $response->json('promptFeedback.blockReason'))) {
            throw new RuntimeException("Gemini blocked the prompt: {$blocked}.");
        }

        $text = '';

        // Thought parts are the model's reasoning, not the answer.
        foreach ((array) $response->json('candidates.0.content.parts', []) as $part) {
            if (isset($part['text']) && empty($part['thought'])) {
                $text .= $part['text'];
            }
        }

        if ($text === '') {
            $reason = $response->json('candidates.0.finishReason') ?? 'unknown';

            throw new RuntimeException("Gemini response did not include text (finish reason: {$reason}).");
        }

        return $text;
    }

    /**
     * Generate an image with a Gemini image model.
     *
     * @return array{url?: string|null, base64?: string|null, error?: string}
     */
    public function generateImage(string $prompt, string $aspect = '16:9', ?int $timeout = null, int $attempts = 2): array
    {
        $apiKey = AiGeneratorConfig::apiKey(Provider::Gemini);

        if ($apiKey === null) {
            return ['error' => 'GEMINI_API_KEY is not set.'];
        }

        $response = $this->postImage(
            $this->authenticated($this->http($timeout, $attempts), $apiKey),
            $this->generateUrl((string) AiGeneratorConfig::imageModel(Provider::Gemini)),
            [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'responseModalities' => ['TEXT', 'IMAGE'],
                    'imageConfig' => ['aspectRatio' => $aspect],
                ],
            ],
        );

        if (is_string($response)) {
            return ['error' => $response];
        }

        // A text part may come before the image.
        foreach ((array) $response->json('candidates.0.content.parts', []) as $part) {
            $data = $part['inlineData']['data'] ?? $part['inline_data']['data'] ?? null;

            if (is_string($data) && $data !== '') {
                return ['url' => null, 'base64' => $data];
            }
        }

        return ['error' => 'Gemini returned no image.'];
    }

    private function authenticated(PendingRequest $http, string $apiKey): PendingRequest
    {
        return $http->withHeaders(['x-goog-api-key' => $apiKey]);
    }

    private function generateUrl(string $model): string
    {
        $model = str_starts_with($model, 'models/') ? substr($model, 7) : $model;

        // The model id ends up in the path, so it is encoded: a model id from an MCP client or a
        // form must not be able to point the request, and the API key, at another endpoint.
        return $this->url('models/'.rawurlencode($model).':generateContent');
    }
}
