<?php

use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\AiGeneratorManager;
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\Drivers\AnthropicDriver;
use Darvis\LaravelAiGenerator\Drivers\GeminiDriver;
use Darvis\LaravelAiGenerator\Drivers\OpenAiDriver;
use Darvis\LaravelAiGenerator\Drivers\XaiDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function failingDriver(string $message): AiContentDriver
{
    return new class($message) implements AiContentDriver
    {
        public function __construct(private string $message) {}

        public function generate(ContentRequest $request): ContentResult
        {
            throw new RuntimeException($this->message);
        }
    };
}

function okDriver(string $title): AiContentDriver
{
    return new class($title) implements AiContentDriver
    {
        public function __construct(private string $title) {}

        public function generate(ContentRequest $request): ContentResult
        {
            return new ContentResult($this->title, 'Intro', 'Text', 'SEO', 'Description');
        }
    };
}

test('the manager builds every driver by name and by alias', function (string $name, string $class) {
    expect(app(AiGeneratorManager::class)->driver($name))->toBeInstanceOf($class);
})->with([
    ['openai', OpenAiDriver::class],
    ['chatgpt', OpenAiDriver::class],
    ['anthropic', AnthropicDriver::class],
    ['claude', AnthropicDriver::class],
    ['gemini', GeminiDriver::class],
    ['xai', XaiDriver::class],
    ['grok', XaiDriver::class],
]);

test('the default driver follows the config, also as an alias', function () {
    config(['ai-generator.driver' => 'claude']);

    expect(app(AiContentDriver::class))->toBeInstanceOf(AnthropicDriver::class);
});

test('a driver registered with extend() is used by name', function () {
    app(AiGeneratorManager::class)->extend('fake', fn ($app, ?string $model) => okDriver('Fake '.($model ?? 'default')));

    expect(app(AiGenerator::class)->using('fake')->generate(new ContentRequest(topic: 'x'))->title)->toBe('Fake default')
        ->and(app(AiGenerator::class)->using('fake', 'm1')->generate(new ContentRequest(topic: 'x'))->title)->toBe('Fake m1');
});

test('an unknown name throws', function () {
    expect(fn () => app(AiGenerator::class)->using('mistral'))
        ->toThrow(RuntimeException::class, 'Unsupported AI driver: mistral');
});

test('the image driver follows the config, then the text driver, then OpenAI', function () {
    $manager = app(AiGeneratorManager::class);

    expect($manager->imageDriverFor($manager->driver('gemini')))->toBeInstanceOf(GeminiDriver::class)
        ->and($manager->imageDriverFor($manager->driver('anthropic')))->toBeInstanceOf(OpenAiDriver::class);

    config(['ai-generator.image_driver' => 'grok']);

    expect($manager->imageDriverFor($manager->driver('gemini')))->toBeInstanceOf(XaiDriver::class);

    config(['ai-generator.image_driver' => 'claude']);

    expect($manager->imageDriverFor($manager->driver('openai')))->toBeNull();
});

test('generateImage uses the driver it is given and explains when that driver has no images', function () {
    config(['ai-generator.drivers.gemini.api_key' => 'g']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['inlineData' => ['data' => 'aW1n']]]]]]])]);

    expect(app(AiGenerator::class)->generateImage('a cat', 'photo', '1:1', 'gemini'))->toBe(['url' => null, 'base64' => 'aW1n'])
        ->and(app(AiGenerator::class)->generateImage('a cat', 'photo', '1:1', 'claude'))
        ->toBe(['error' => 'Claude (Anthropic) cannot generate images. Use openai, gemini or xai as image driver.'])
        ->and(app(AiGenerator::class)->generateImage('a cat', 'photo', '1:1', 'nope'))
        ->toBe(['error' => 'Unsupported AI driver: nope']);

    Http::assertSent(fn (Request $request) => $request['contents'][0]['parts'][0]['text'] === 'Professional high-quality photograph of a cat. No text or watermarks.');
});

test('a failed text call moves on to the fallback drivers in order, skipping one without a key', function () {
    config([
        'ai-generator.fallbacks' => 'gemini, claude',
        'ai-generator.drivers.anthropic.api_key' => 'sk-ant',
        'ai-generator.drivers.gemini.api_key' => null,
    ]);

    $manager = app(AiGeneratorManager::class);
    $manager->extend('anthropic', fn () => okDriver('From Claude'));

    $generator = new AiGenerator(failingDriver('OpenAI is down'), $manager);

    expect($generator->generate(new ContentRequest(topic: 'x'))->title)->toBe('From Claude');
});

test('when every driver fails the message lists them all', function () {
    config([
        'ai-generator.fallbacks' => 'anthropic',
        'ai-generator.drivers.anthropic.api_key' => 'sk-ant',
    ]);

    $manager = app(AiGeneratorManager::class);
    $manager->extend('anthropic', fn () => failingDriver('Claude is down'));

    expect(fn () => (new AiGenerator(failingDriver('OpenAI is down'), $manager))->generate(new ContentRequest(topic: 'x')))
        ->toThrow(RuntimeException::class, 'Every AI driver failed. openai: OpenAI is down | anthropic: Claude is down');
});

test('without fallbacks, and after using(), the original exception comes through', function () {
    expect(fn () => (new AiGenerator(failingDriver('OpenAI is down')))->generate(new ContentRequest(topic: 'x')))
        ->toThrow(RuntimeException::class, 'OpenAI is down');

    config(['ai-generator.fallbacks' => 'openai', 'ai-generator.drivers.anthropic.api_key' => 'k']);
    app(AiGeneratorManager::class)->extend('anthropic', fn () => failingDriver('Claude is down'));

    expect(fn () => app(AiGenerator::class)->using('anthropic')->generate(new ContentRequest(topic: 'x')))
        ->toThrow(RuntimeException::class, 'Claude is down');
});
