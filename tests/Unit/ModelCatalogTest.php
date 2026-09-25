<?php

use Darvis\LaravelAiGenerator\Support\ModelCatalog;
use Darvis\LaravelAiGenerator\Support\Provider;

test('OpenAI models are split into text and image, without audio, embeddings and old completions', function () {
    $models = ModelCatalog::classify(Provider::OpenAi, [
        'gpt-4.1-mini', 'gpt-6-luna', 'o4-mini', 'gpt-image-1', 'dall-e-3', 'text-embedding-3-large',
        'whisper-1', 'gpt-4o-realtime-preview', 'tts-1', 'davinci-002', 'omni-moderation-latest',
    ]);

    expect($models)->toBe([
        'text' => ['o4-mini', 'gpt-6-luna', 'gpt-4.1-mini'],
        'image' => ['gpt-image-1', 'dall-e-3'],
    ]);
});

test('Claude has no image models, and Gemini keeps its image models apart', function () {
    expect(ModelCatalog::classify(Provider::Anthropic, ['claude-sonnet-5', 'claude-haiku-4-5']))
        ->toBe(['text' => ['claude-sonnet-5', 'claude-haiku-4-5'], 'image' => []])
        ->and(ModelCatalog::classify(Provider::Gemini, ['gemini-3.8-flash', 'gemini-3.1-flash-image', 'gemini-embedding-001', 'gemini-3.5-flash-live']))
        ->toBe(['text' => ['gemini-3.8-flash'], 'image' => ['gemini-3.1-flash-image']]);
});
