<?php

use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\Drivers\OpenAiDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('DALL-E 3 gets a size that follows the aspect ratio and base64 output', function () {
    config(['ai-generator.drivers.openai.image_model' => 'dall-e-3']);

    expect(OpenAiDriver::imagePayload('A lighthouse', '16:9'))->toBe([
        'model' => 'dall-e-3',
        'prompt' => 'A lighthouse',
        'size' => '1792x1024',
        'response_format' => 'b64_json',
    ])
        ->and(OpenAiDriver::imagePayload('A lighthouse', '4:5')['size'])->toBe('1024x1792')
        ->and(OpenAiDriver::imagePayload('A lighthouse', '1:1')['size'])->toBe('1024x1024');
});

test('other image models get a square image and no response format', function () {
    expect(OpenAiDriver::imagePayload('A lighthouse', '16:9'))->toBe([
        'model' => 'gpt-image-1',
        'prompt' => 'A lighthouse',
        'size' => '1024x1024',
    ]);
});

test('generateImage sends the styled prompt and returns the image as base64', function () {
    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => 'aGVsbG8=']]]),
    ]);

    $image = app(AiGenerator::class)->generateImage('a lighthouse at dusk', 'illustration', '1:1');

    expect($image)->toBe(['url' => null, 'base64' => 'aGVsbG8=']);

    Http::assertSent(fn (Request $request) => $request['prompt'] === 'Digital illustration of a lighthouse at dusk. No text or watermarks.'
        && $request['model'] === 'gpt-image-1'
        && $request->hasHeader('Authorization', 'Bearer test-api-key'));
});

test('generateImage reports a missing api key instead of calling OpenAI', function () {
    Http::fake();
    config(['ai-generator.drivers.openai.api_key' => null]);

    expect(app(AiGenerator::class)->generateImage('a lighthouse'))
        ->toBe(['error' => 'OPENAI_API_KEY is not set.']);

    Http::assertNothingSent();
});
