<?php

namespace Darvis\LaravelAiGenerator\Drivers;

use Closure;
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\Contracts\AiImageDriver;
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Darvis\LaravelAiGenerator\Support\ContentPrompt;
use Darvis\LaravelAiGenerator\Support\Provider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * What the built-in drivers share: the prompt, the key check, the HTTP settings, the error messages
 * and the image step. A driver only sends the prompt and the JSON schema and returns the JSON text.
 *
 * Extend AiContentDriver for a driver of your own, not this class: it is not part of the public API.
 *
 * @internal
 */
abstract class Driver implements AiContentDriver
{
    /**
     * @param  string|null  $model  The text model, or null for the configured one
     * @param  (Closure(AiContentDriver): ?AiImageDriver)|null  $images  Finds the image driver for this text driver
     */
    public function __construct(
        protected readonly ?string $model = null,
        private readonly ?Closure $images = null,
    ) {}

    abstract public function provider(): Provider;

    /**
     * Send the prompt with the JSON schema and return the JSON text of the answer.
     *
     * @param  array<string, mixed>  $schema
     *
     * @throws RuntimeException On a connection or request failure, or an answer without text
     */
    abstract protected function requestJson(string $apiKey, string $prompt, array $schema): string;

    /**
     * Generate the text with this provider, and the image with the image driver.
     *
     * @throws RuntimeException If the API key is missing or the text call fails
     */
    public function generate(ContentRequest $request): ContentResult
    {
        $apiKey = $this->requireApiKey();

        $json = ContentPrompt::decode(
            $this->requestJson($apiKey, ContentPrompt::build($request), ContentPrompt::schema()),
            $this->provider()->shortName(),
        );

        $result = ContentPrompt::toResult($json, $request, $this->provider()->value, $this->model());

        if (! $request->includeImage || empty($result->imagePrompt)) {
            return $result;
        }

        $images = $this->images !== null
            ? ($this->images)($this)
            : ($this instanceof AiImageDriver ? $this : null);

        if ($images === null) {
            return ContentPrompt::withImage($result, [
                'error' => $this->provider()->shortName().' cannot generate images. Set AI_GENERATOR_IMAGE_DRIVER to openai, gemini or xai.',
            ]);
        }

        $image = $images->generateImage($result->imagePrompt, $request->imageAspect ?? '16:9');

        if (! empty($image['error'])) {
            $name = $images instanceof self ? $images->provider()->shortName() : 'Image driver';
            $image = ['error' => "{$name} Image Error: {$image['error']}"];
        }

        return ContentPrompt::withImage($result, $image);
    }

    /**
     * The text model this driver writes with.
     */
    public function model(): string
    {
        return $this->model ?? AiGeneratorConfig::model($this->provider());
    }

    /**
     * @throws RuntimeException When the API key is not set
     */
    protected function requireApiKey(): string
    {
        return AiGeneratorConfig::apiKey($this->provider())
            ?? throw new RuntimeException($this->provider()->apiKeyEnv().' is not set.');
    }

    protected function url(string $path): string
    {
        return AiGeneratorConfig::baseUrl($this->provider()).'/'.ltrim($path, '/');
    }

    /**
     * A request with the timeout and the attempts of this provider, without authentication.
     */
    protected function http(?int $timeout = null, int $attempts = 2): PendingRequest
    {
        $timeout ??= AiGeneratorConfig::timeout($this->provider());

        return Http::timeout($timeout)
            ->connectTimeout(min(30, $timeout))
            ->retry($attempts, 250)
            ->acceptJson()
            ->asJson();
    }

    /**
     * Post and turn a failure into a RuntimeException that names the provider.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws RuntimeException
     */
    protected function post(PendingRequest $http, string $url, array $payload): Response
    {
        $name = $this->provider()->shortName();

        try {
            return $http->post($url, $payload)->throw();
        } catch (ConnectionException $e) {
            throw new RuntimeException("{$name} connection failed: ".$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new RuntimeException("{$name} request failed: ".$e->getMessage(), 0, $e);
        }
    }

    /**
     * Post an image request and return the error message instead of throwing, because an image
     * is nice-to-have: the text of generate() must survive a failed image.
     *
     * @param  array<string, mixed>  $payload
     * @return Response|string The response, or the error message
     */
    protected function postImage(PendingRequest $http, string $url, array $payload): Response|string
    {
        try {
            return $http->post($url, $payload)->throw();
        } catch (RequestException|ConnectionException $e) {
            return $e->getMessage();
        }
    }

    /**
     * The temperature to send, as an array to merge into the payload.
     *
     * @return array<string, float>
     */
    protected function temperature(string $key = 'temperature'): array
    {
        $temperature = AiGeneratorConfig::temperature($this->provider());

        return $temperature === null ? [] : [$key => $temperature];
    }
}
