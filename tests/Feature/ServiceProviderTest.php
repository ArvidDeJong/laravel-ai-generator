<?php

use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\Drivers\OpenAiDriver;

test('service provider registers ai content driver', function () {
    $driver = app(AiContentDriver::class);

    expect($driver)->toBeInstanceOf(OpenAiDriver::class);
});

test('service provider registers ai generator as singleton', function () {
    $generator1 = app(AiGenerator::class);
    $generator2 = app(AiGenerator::class);

    expect($generator1)->toBe($generator2);
});

test('service provider registers alias', function () {
    $generator = app('ai-generator');

    expect($generator)->toBeInstanceOf(AiGenerator::class);
});

test('config is merged', function () {
    expect(config('ai-generator.driver'))->toBe('openai')
        ->and(config('ai-generator.default_language'))->toBe('nl')
        ->and(config('ai-generator.defaults.tone'))->toBe('informal');
});

test('throws exception for unsupported driver', function () {
    config(['ai-generator.driver' => 'unsupported']);

    // Clear the singleton to force re-resolution
    app()->forgetInstance(AiContentDriver::class);

    expect(fn () => app(AiContentDriver::class))
        ->toThrow(RuntimeException::class, 'Unsupported AI driver: unsupported');
});
