<?php

namespace Darvis\LaravelAiGenerator\Drivers;

use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Darvis\LaravelAiGenerator\Support\Provider;
use RuntimeException;

/**
 * Claude driver, through the Anthropic Messages API with structured outputs.
 *
 * Claude has no image model: with this driver the image of generate() comes from the configured
 * image driver, OpenAI unless AI_GENERATOR_IMAGE_DRIVER says otherwise.
 *
 * @see https://platform.claude.com/docs/en/build-with-claude/structured-outputs
 */
final class AnthropicDriver extends Driver
{
    public function provider(): Provider
    {
        return Provider::Anthropic;
    }

    /**
     * @param  array<string, mixed>  $schema
     *
     * @throws RuntimeException On connection or request failure, or an incomplete answer
     */
    protected function requestJson(string $apiKey, string $prompt, array $schema): string
    {
        $http = $this->http()->withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => AiGeneratorConfig::anthropicVersion(),
        ]);

        $response = $this->post($http, $this->url('messages'), [
            'model' => $this->model(),
            'max_tokens' => AiGeneratorConfig::anthropicMaxTokens(),
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            // The newest Claude models reject a temperature, so it is only sent when configured.
            ...$this->temperature(),
            'output_config' => [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => $schema,
                ],
            ],
        ]);

        $stopReason = $response->json('stop_reason');

        if ($stopReason === 'max_tokens') {
            throw new RuntimeException('Claude stopped before the answer was complete. Raise ANTHROPIC_MAX_TOKENS or lower maxWords.');
        }

        if ($stopReason === 'refusal') {
            throw new RuntimeException('Claude declined to write about this topic.');
        }

        // Thinking blocks can come first, so the text block is found by type.
        foreach ((array) $response->json('content', []) as $block) {
            if (($block['type'] ?? null) === 'text' && isset($block['text'])) {
                return (string) $block['text'];
            }
        }

        throw new RuntimeException('Claude response did not include a text block.');
    }
}
