<?php

namespace Darvis\LaravelAiGenerator\Contracts;

/**
 * A driver that can make an image. The OpenAI, Gemini and Grok drivers implement it; Claude has no
 * image model, so with Claude as text driver the image comes from the configured image driver.
 *
 * This is a separate contract, so a custom text driver does not have to make images.
 */
interface AiImageDriver
{
    /**
     * Generate one image. Never throws: a failure comes back under the `error` key.
     *
     * @param  string  $prompt  The complete image prompt, in English
     * @param  string  $aspect  Aspect ratio: 1:1, 4:5 or 16:9; a model that cannot follow it returns a square image
     * @param  int|null  $timeout  Seconds per attempt, or null for the timeout of the driver
     * @param  int  $attempts  How often the request is tried in total
     * @return array{url?: string|null, base64?: string|null, error?: string}
     */
    public function generateImage(string $prompt, string $aspect = '16:9', ?int $timeout = null, int $attempts = 2): array;
}
