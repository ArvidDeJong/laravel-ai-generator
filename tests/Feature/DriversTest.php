<?php

use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Every built-in driver against a faked API in the shape its provider documents. No real API is
 * called: Http::preventStrayRequests() makes an unfaked URL fail the test.
 */
function contentJson(array $overrides = []): string
{
    return json_encode([
        'title' => 'Title',
        'intro' => 'Intro',
        'text' => '<p>Text</p>',
        'seo_title' => 'SEO title',
        'seo_description' => 'SEO description',
        'image_prompt' => 'A lighthouse',
        ...$overrides,
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'ai-generator.drivers.anthropic.api_key' => 'sk-ant-test',
        'ai-generator.drivers.gemini.api_key' => 'gemini-test',
        'ai-generator.drivers.xai.api_key' => 'xai-test',
    ]);
});

test('Claude gets the prompt with a JSON schema and no temperature, and its text block is read', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [
                ['type' => 'thinking', 'thinking' => '...'],
                ['type' => 'text', 'text' => contentJson()],
            ],
            'stop_reason' => 'end_turn',
        ]),
    ]);

    $result = app(AiGenerator::class)->using('claude')->generate(new ContentRequest(topic: 'Lighthouses', includeImage: false));

    expect($result->title)->toBe('Title')
        ->and($result->text)->toBe('<p>Text</p>')
        ->and($result->imagePrompt)->toBeNull()
        ->and($result->driver)->toBe('anthropic')
        ->and($result->model)->toBe('claude-sonnet-5');

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-api-key', 'sk-ant-test')
        && $request->hasHeader('anthropic-version', '2023-06-01')
        && $request['model'] === 'claude-sonnet-5'
        && $request['max_tokens'] === 8192
        && $request['output_config']['format']['type'] === 'json_schema'
        && $request['output_config']['format']['schema']['required'] === ['title', 'intro', 'text', 'seo_title', 'seo_description', 'image_prompt']
        && ! isset($request['temperature']));
});

test('Claude sends a temperature only when one is configured', function () {
    config(['ai-generator.drivers.anthropic.temperature' => '0.3']);

    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => contentJson()]]])]);

    app(AiGenerator::class)->using('anthropic')->generate(new ContentRequest(topic: 'x', includeImage: false));

    Http::assertSent(fn (Request $request) => $request['temperature'] === 0.3);
});

test('Claude explains a cut off answer and a refusal', function (string $stopReason, string $message) {
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '{"ti']], 'stop_reason' => $stopReason])]);

    expect(fn () => app(AiGenerator::class)->using('anthropic')->generate(new ContentRequest(topic: 'x', includeImage: false)))
        ->toThrow(RuntimeException::class, $message);
})->with([
    ['max_tokens', 'Raise ANTHROPIC_MAX_TOKENS'],
    ['refusal', 'Claude declined'],
]);

test('with Claude as text driver the image comes from OpenAI', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => contentJson()]]]),
        'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => 'aW1n']]]),
    ]);

    $result = app(AiGenerator::class)->using('anthropic')->generate(new ContentRequest(topic: 'x'));

    expect($result->imageBase64)->toBe('aW1n')
        ->and($result->hasError())->toBeFalse();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.openai.com')
        && $request['prompt'] === 'A lighthouse');
});

test('with Claude as text driver and no OpenAI key the text survives and the error says why', function () {
    config(['ai-generator.drivers.openai.api_key' => null]);

    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => contentJson()]]])]);

    $result = app(AiGenerator::class)->using('anthropic')->generate(new ContentRequest(topic: 'x'));

    expect($result->title)->toBe('Title')
        ->and($result->errorMessage)->toBe('OpenAI Image Error: OPENAI_API_KEY is not set.');
});

test('Gemini gets a JSON schema, skips thought parts and makes its own image', function () {
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [
                    ['text' => 'thinking...', 'thought' => true],
                    ['text' => "```json\n".contentJson()."\n```"],
                ]],
                'finishReason' => 'STOP',
            ]],
        ]),
        'generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-image:generateContent' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [
                    ['text' => 'Here is your image'],
                    ['inlineData' => ['mimeType' => 'image/png', 'data' => 'cG5n']],
                ]],
            ]],
        ]),
    ]);

    $result = app(AiGenerator::class)->using('gemini')->generate(new ContentRequest(topic: 'x', imageAspect: '4:5'));

    expect($result->title)->toBe('Title')
        ->and($result->imageBase64)->toBe('cG5n')
        ->and($result->driver)->toBe('gemini');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'gemini-3.8-flash')
        && $request->hasHeader('x-goog-api-key', 'gemini-test')
        && $request['generationConfig']['responseMimeType'] === 'application/json'
        && isset($request['generationConfig']['responseJsonSchema']));

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'gemini-3.1-flash-image')
        && $request['generationConfig']['imageConfig']['aspectRatio'] === '4:5');
});

test('Gemini reports a blocked prompt', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']])]);

    expect(fn () => app(AiGenerator::class)->using('gemini')->generate(new ContentRequest(topic: 'x', includeImage: false)))
        ->toThrow(RuntimeException::class, 'Gemini blocked the prompt: SAFETY.');
});

test('Grok gets a strict JSON schema and makes its own image in the nearest aspect ratio', function () {
    Http::fake([
        'api.x.ai/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => contentJson()]]]]),
        'api.x.ai/v1/images/generations' => Http::response(['data' => [['b64_json' => 'Z3Jvaw==']]]),
    ]);

    $result = app(AiGenerator::class)->using('grok')->generate(new ContentRequest(topic: 'x', imageAspect: '4:5'));

    expect($result->imageBase64)->toBe('Z3Jvaw==')
        ->and($result->driver)->toBe('xai')
        ->and($result->model)->toBe('grok-4.7');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'chat/completions')
        && $request->hasHeader('Authorization', 'Bearer xai-test')
        && $request['response_format']['type'] === 'json_schema'
        && $request['response_format']['json_schema']['strict'] === true);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'images/generations')
        && $request['model'] === 'grok-imagine-image-2.0'
        && $request['aspect_ratio'] === '3:4'
        && $request['response_format'] === 'b64_json');
});

test('OpenAI keeps its request shape, and a model asked for with using() is sent', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => contentJson()]]]]]),
    ]);

    $result = app(AiGenerator::class)->using('chatgpt', 'gpt-6-luna')->generate(new ContentRequest(topic: 'x', includeImage: false));

    expect($result->model)->toBe('gpt-6-luna');

    Http::assertSent(fn (Request $request) => $request['model'] === 'gpt-6-luna'
        && $request['temperature'] === 0.7
        && $request['text']['format']['strict'] === true);
});

test('an empty OpenAI temperature sends none, for models that reject one', function () {
    config(['ai-generator.drivers.openai.temperature' => '']);

    Http::fake(['api.openai.com/*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => contentJson()]]]]])]);

    app(AiGenerator::class)->generate(new ContentRequest(topic: 'x', includeImage: false));

    Http::assertSent(fn (Request $request) => ! isset($request['temperature']));
});

test('a missing key names the environment variable to set', function (string $driver, string $variable) {
    config(["ai-generator.drivers.{$driver}.api_key" => null]);
    Http::fake();

    expect(fn () => app(AiGenerator::class)->using($driver)->generate(new ContentRequest(topic: 'x', includeImage: false)))
        ->toThrow(RuntimeException::class, "{$variable} is not set.");

    Http::assertNothingSent();
})->with([
    ['anthropic', 'ANTHROPIC_API_KEY'],
    ['gemini', 'GEMINI_API_KEY'],
    ['openai', 'OPENAI_API_KEY'],
    ['xai', 'XAI_API_KEY'],
]);

test('a failed request names the provider', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'invalid x-api-key']], 401)]);

    expect(fn () => app(AiGenerator::class)->using('anthropic')->generate(new ContentRequest(topic: 'x', includeImage: false)))
        ->toThrow(RuntimeException::class, 'Claude request failed:');
});
