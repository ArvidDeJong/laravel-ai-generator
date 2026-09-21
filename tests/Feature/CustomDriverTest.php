<?php

use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\Facades\AiGenerator;
use Illuminate\Support\ServiceProvider;

/**
 * docs/custom-drivers.md tells a host app how to plug in its own driver. This runs exactly that
 * recipe, so the page cannot drift into instructions that throw.
 */
test('a custom driver registered the way the docs describe is used', function () {
    config(['ai-generator.driver' => 'fake']);

    app()->register(new class(app()) extends ServiceProvider
    {
        public function register(): void
        {
            if (config('ai-generator.driver') === 'fake') {
                $this->app->singleton(AiContentDriver::class, fn () => new class implements AiContentDriver
                {
                    public function generate(ContentRequest $request): ContentResult
                    {
                        return new ContentResult(
                            title: 'From the fake driver',
                            intro: 'Intro',
                            text: '<p>Text</p>',
                            seoTitle: 'SEO',
                            seoDescription: 'Description',
                        );
                    }
                });
            }
        }
    });

    $result = AiGenerator::generate(new ContentRequest(topic: 'Anything', includeImage: false));

    expect($result->title)->toBe('From the fake driver');
});

test('extending the package binding does not work for a driver name the package does not know', function () {
    // The docs used to recommend extend(). The package binding is built first and throws on an
    // unknown name, so the extender never runs. Kept as a test so the docs don't go back to it.
    config(['ai-generator.driver' => 'fake']);

    app()->extend(AiContentDriver::class, fn ($driver) => $driver);

    expect(fn () => app(AiContentDriver::class))
        ->toThrow(RuntimeException::class, 'Unsupported AI driver: fake');
});
