<?php

use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Darvis\LaravelAiGenerator\Support\Provider;

/**
 * AiGeneratorConfig is the one place that reads the package config. These tests guard the two
 * things that go wrong once a default is written down twice: an accessor that disagrees with the
 * config file, and a caller that reaches past the accessor and keeps its own stale fallback.
 */
function aiGeneratorRoot(string $path = ''): string
{
    return dirname(__DIR__, 2).($path === '' ? '' : '/'.$path);
}

test('the accessors return the values the config file ships', function () {
    $config = require aiGeneratorRoot('config/ai-generator.php');
    $openAi = $config['drivers']['openai'];

    expect(AiGeneratorConfig::driver())->toBe($config['driver'])
        ->and(AiGeneratorConfig::defaultLanguage())->toBe($config['default_language'])
        ->and(AiGeneratorConfig::defaultMaxWords())->toBe((int) $config['defaults']['max_words'])
        ->and(AiGeneratorConfig::defaultReadingLevel())->toBe($config['defaults']['reading_level'])
        ->and(AiGeneratorConfig::defaultTone())->toBe($config['defaults']['tone'])
        ->and(AiGeneratorConfig::openAiBaseUrl())->toBe($openAi['base_url'])
        ->and(AiGeneratorConfig::openAiImageModel())->toBe($openAi['image_model'])
        ->and(AiGeneratorConfig::openAiModel())->toBe($openAi['model'])
        ->and(AiGeneratorConfig::openAiTemperature())->toBe((float) $openAi['temperature'])
        ->and(AiGeneratorConfig::openAiTimeout())->toBe((int) $openAi['timeout']);
});

test('every provider has a config section, and its accessors return what the config file ships', function (Provider $provider) {
    $config = require aiGeneratorRoot('config/ai-generator.php');
    $section = $config['drivers'][$provider->value];

    expect(AiGeneratorConfig::baseUrl($provider))->toBe($section['base_url'])
        ->and($provider->defaultBaseUrl())->toBe($section['base_url'])
        ->and(AiGeneratorConfig::model($provider))->toBe($section['model'])
        ->and($provider->defaultModel())->toBe($section['model'])
        ->and(AiGeneratorConfig::imageModel($provider))->toBe($section['image_model'] ?? null)
        ->and($provider->defaultImageModel())->toBe($section['image_model'] ?? null)
        ->and(AiGeneratorConfig::temperature($provider))->toBe($section['temperature'] === null ? null : (float) $section['temperature'])
        ->and(AiGeneratorConfig::timeout($provider))->toBe((int) $section['timeout']);
})->with(Provider::cases());

test('the multi-provider settings default to no image driver, no fallbacks and the MCP server on', function () {
    expect(AiGeneratorConfig::imageDriver())->toBeNull()
        ->and(AiGeneratorConfig::fallbacks())->toBe([])
        ->and(AiGeneratorConfig::mcpEnabled())->toBeTrue()
        ->and(AiGeneratorConfig::mcpHandle())->toBe('ai-generator')
        ->and(AiGeneratorConfig::anthropicMaxTokens())->toBe(8192);

    config(['ai-generator.fallbacks' => ' anthropic, ,gemini ', 'ai-generator.mcp.enabled' => 'false']);

    expect(AiGeneratorConfig::fallbacks())->toBe(['anthropic', 'gemini'])
        ->and(AiGeneratorConfig::mcpEnabled())->toBeFalse();
});

test('the accessors follow a changed setting and cast values that come from the env as strings', function () {
    config([
        'ai-generator.defaults.max_words' => '300',
        'ai-generator.drivers.openai.temperature' => '0.2',
        'ai-generator.drivers.openai.timeout' => '90',
        'ai-generator.drivers.openai.base_url' => 'https://proxy.example.com/v1/',
    ]);

    expect(AiGeneratorConfig::defaultMaxWords())->toBe(300)
        ->and(AiGeneratorConfig::openAiTemperature())->toBe(0.2)
        ->and(AiGeneratorConfig::openAiTimeout())->toBe(90)
        ->and(AiGeneratorConfig::openAiBaseUrl())->toBe('https://proxy.example.com/v1');
});

test('an empty api key counts as no api key', function () {
    config(['ai-generator.drivers.openai.api_key' => '']);
    expect(AiGeneratorConfig::openAiApiKey())->toBeNull();

    config(['ai-generator.drivers.openai.api_key' => 'sk-test']);
    expect(AiGeneratorConfig::openAiApiKey())->toBe('sk-test');
});

test('nothing outside the accessor reads the package config', function () {
    $offenders = [];

    foreach (['src', 'resources', 'routes'] as $directory) {
        $path = aiGeneratorRoot($directory);

        if (! is_dir($path)) {
            continue;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(aiGeneratorRoot().'/', '', $file->getPathname());

            // The accessor is where the reading happens, and the Boost guideline quotes the call
            // it tells you not to write.
            if (str_ends_with($relative, 'AiGeneratorConfig.php') || str_starts_with($relative, 'resources/boost/')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match("/(config\\(|Config::get\\()['\"]ai-generator\\./", $contents)) {
                $offenders[] = $relative;
            }
        }
    }

    expect($offenders)->toBe([], 'these read the config directly instead of through AiGeneratorConfig');
});

test('the config keys are in alphabetical order, at every level', function () {
    $walk = function (array $config, string $trail) use (&$walk): void {
        $keys = array_keys($config);

        if ($keys !== array_filter($keys, 'is_string')) {
            return;
        }

        $sorted = $keys;
        sort($sorted);

        expect($keys)->toBe($sorted, "the keys in '{$trail}' are not in alphabetical order");

        foreach ($config as $key => $value) {
            if (is_array($value) && $value !== []) {
                $walk($value, $trail.'.'.$key);
            }
        }
    };

    $walk(require aiGeneratorRoot('config/ai-generator.php'), 'ai-generator');
});
