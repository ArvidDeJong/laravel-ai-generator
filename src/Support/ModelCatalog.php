<?php

namespace Darvis\LaravelAiGenerator\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Asks a provider which models an API key may use. The list endpoints cost nothing, which makes
 * this the check of a key in the install wizard and in ai-generator:status.
 *
 * @internal
 */
final class ModelCatalog
{
    /**
     * Parts of a model id that mark a model that does not write text or draw images.
     */
    private const NOT_FOR_CONTENT = [
        'embed', 'tts', 'audio', 'realtime', 'transcribe', 'whisper', 'moderation', 'search',
        'davinci', 'babbage', 'live', 'veo', 'aqa', 'computer-use', 'robotics', 'video', 'speech',
    ];

    /**
     * The text and image models the key may use, newest name first.
     *
     * @return array{text: list<string>, image: list<string>}
     *
     * @throws RuntimeException When the key is refused or the provider cannot be reached
     */
    public static function fetch(Provider $provider, string $apiKey, ?string $baseUrl = null): array
    {
        $baseUrl = rtrim($baseUrl ?? AiGeneratorConfig::baseUrl($provider), '/');

        try {
            $ids = match ($provider) {
                Provider::Anthropic => self::ids(
                    self::http()->withHeaders([
                        'x-api-key' => $apiKey,
                        'anthropic-version' => AiGeneratorConfig::anthropicVersion(),
                    ])->get("{$baseUrl}/models", ['limit' => 1000])->throw()->json('data', []),
                    'id',
                ),
                Provider::Gemini => self::geminiIds(
                    self::http()->withHeaders(['x-goog-api-key' => $apiKey])
                        ->get("{$baseUrl}/models", ['pageSize' => 1000])->throw()->json('models', []),
                ),
                Provider::OpenAi, Provider::Xai => self::ids(
                    self::http()->withToken($apiKey)->get("{$baseUrl}/models")->throw()->json('data', []),
                    'id',
                ),
            };
        } catch (RequestException $e) {
            $status = $e->response->status();

            throw new RuntimeException(in_array($status, [400, 401, 403], true)
                ? "{$provider->shortName()} refused the API key (HTTP {$status})."
                : "{$provider->shortName()} request failed: {$e->getMessage()}", 0, $e);
        } catch (ConnectionException $e) {
            throw new RuntimeException("{$provider->shortName()} connection failed: {$e->getMessage()}", 0, $e);
        }

        return self::classify($provider, $ids);
    }

    /**
     * Split model ids into text and image models, and drop the ones for audio, embeddings and such.
     *
     * @param  list<string>  $ids
     * @return array{text: list<string>, image: list<string>}
     */
    public static function classify(Provider $provider, array $ids): array
    {
        $text = [];
        $image = [];

        foreach (array_unique($ids) as $id) {
            $lower = strtolower($id);

            if (str_contains($lower, 'image') || str_starts_with($lower, 'dall-e') || str_starts_with($lower, 'imagen')) {
                if ($provider->supportsImages()) {
                    $image[] = $id;
                }

                continue;
            }

            if (self::containsAny($lower, self::NOT_FOR_CONTENT)) {
                continue;
            }

            if ($provider === Provider::OpenAi && ! preg_match('/^(gpt-|chatgpt-|o\d)/', $lower)) {
                continue;
            }

            $text[] = $id;
        }

        rsort($text, SORT_NATURAL);
        rsort($image, SORT_NATURAL);

        return ['text' => $text, 'image' => $image];
    }

    private static function http(): PendingRequest
    {
        return Http::timeout(20)->connectTimeout(10)->acceptJson();
    }

    /**
     * @return list<string>
     */
    private static function ids(mixed $items, string $key): array
    {
        $ids = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item) && is_string($item[$key] ?? null)) {
                $ids[] = $item[$key];
            }
        }

        return $ids;
    }

    /**
     * Gemini lists every model; only the ones that answer generateContent are of use here.
     *
     * @return list<string>
     */
    private static function geminiIds(mixed $models): array
    {
        $ids = [];

        foreach (is_array($models) ? $models : [] as $model) {
            if (! is_array($model) || ! is_string($model['name'] ?? null)) {
                continue;
            }

            if (! in_array('generateContent', (array) ($model['supportedGenerationMethods'] ?? []), true)) {
                continue;
            }

            $ids[] = str_starts_with($model['name'], 'models/') ? substr($model['name'], 7) : $model['name'];
        }

        return $ids;
    }

    /**
     * @param  list<string>  $needles
     */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
