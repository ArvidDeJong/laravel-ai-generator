<?php

use Illuminate\Support\Facades\Http;

/**
 * The wizard writes to the .env of the application. Every test points the application at a
 * temporary directory, so the Testbench .env is never touched.
 */
beforeEach(function () {
    Http::preventStrayRequests();

    $this->envDir = sys_get_temp_dir().'/ai-generator-'.uniqid();
    mkdir($this->envDir);
    file_put_contents($this->envDir.'/.env', "APP_NAME=Demo\n");
    app()->useEnvironmentPath($this->envDir);

    // The TestCase sets an OpenAI key; the wizard should start from an application without one.
    config(['ai-generator.drivers.openai.api_key' => null]);
});

afterEach(function () {
    @unlink($this->envDir.'/.env');
    @rmdir($this->envDir);
});

test('a Claude and a ChatGPT key: Claude writes, OpenAI draws, OpenAI takes over on a failure', function () {
    Http::fake([
        'api.anthropic.com/v1/models*' => Http::response(['data' => [['id' => 'claude-sonnet-5'], ['id' => 'claude-opus-5-5']]]),
        'api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-4.1-mini'], ['id' => 'gpt-image-2'], ['id' => 'gpt-image-1'], ['id' => 'whisper-1']]]),
    ]);

    $this->artisan('ai-generator:install')
        ->expectsChoice('Which AI providers do you have an API key for?', ['anthropic', 'openai'], [
            'anthropic' => 'Claude (Anthropic), text only',
            'gemini' => 'Gemini (Google), text and images',
            'openai' => 'ChatGPT (OpenAI), text and images',
            'xai' => 'Grok (xAI), text and images',
        ])
        ->expectsQuestion('ANTHROPIC_API_KEY', 'sk-ant-secret-1234')
        ->expectsChoice('Which Claude model writes the text?', 'claude-opus-5-5', [
            'claude-sonnet-5' => 'claude-sonnet-5 (current)',
            'claude-opus-5-5' => 'claude-opus-5-5',
            '__other__' => 'Another model, type its id',
        ])
        ->expectsQuestion('OPENAI_API_KEY', 'sk-proj-secret-5678')
        ->expectsChoice('Which OpenAI model writes the text?', 'gpt-4.1-mini', [
            'gpt-4.1-mini' => 'gpt-4.1-mini (current)',
            '__other__' => 'Another model, type its id',
        ])
        ->expectsChoice('Which OpenAI model makes images?', 'gpt-image-2', [
            'gpt-image-2' => 'gpt-image-2 (current)',
            'gpt-image-1' => 'gpt-image-1',
            '__other__' => 'Another model, type its id',
        ])
        ->expectsChoice('Which provider writes the text by default?', 'anthropic', [
            'anthropic' => 'Claude (Anthropic)',
            'openai' => 'ChatGPT (OpenAI)',
        ])
        ->expectsChoice('When Claude fails, which providers should take over?', ['openai'], [
            'openai' => 'ChatGPT (OpenAI)',
        ])
        ->expectsConfirmation('Change the default language, tone and reading level?', 'no')
        ->expectsConfirmation('Write these settings to .env?', 'yes')
        ->assertSuccessful();

    $env = file_get_contents($this->envDir.'/.env');

    expect($env)->toStartWith("APP_NAME=Demo\n")
        ->toContain("ANTHROPIC_API_KEY=sk-ant-secret-1234\n")
        ->toContain("ANTHROPIC_MODEL=claude-opus-5-5\n")
        ->toContain("OPENAI_API_KEY=sk-proj-secret-5678\n")
        ->toContain("OPENAI_IMAGE_MODEL=gpt-image-2\n")
        ->toContain("AI_GENERATOR_DRIVER=anthropic\n")
        ->toContain("AI_GENERATOR_IMAGE_DRIVER=openai\n")
        ->toContain("AI_GENERATOR_FALLBACKS=openai\n");
});

test('a refused key can be skipped, and nothing is written when no provider is left', function () {
    Http::fake(['api.x.ai/v1/models' => Http::response(['error' => 'bad key'], 401)]);

    $this->artisan('ai-generator:install')
        ->expectsChoice('Which AI providers do you have an API key for?', ['xai'], [
            'anthropic' => 'Claude (Anthropic), text only',
            'gemini' => 'Gemini (Google), text and images',
            'openai' => 'ChatGPT (OpenAI), text and images',
            'xai' => 'Grok (xAI), text and images',
        ])
        ->expectsQuestion('XAI_API_KEY', 'xai-wrong')
        ->expectsOutputToContain('Grok refused the API key (HTTP 401).')
        ->expectsChoice('What now?', 'skip', [
            'retry' => 'Enter another key',
            'keep' => 'Keep this key without checking it',
            'skip' => 'Skip Grok',
        ])
        ->assertFailed();

    expect(file_get_contents($this->envDir.'/.env'))->toBe("APP_NAME=Demo\n");
});
