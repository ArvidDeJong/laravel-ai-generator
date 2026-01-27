<?php

namespace Darvis\LaravelAiGenerator;

use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\Drivers\OpenAiDriver;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel service provider for the AI Generator package.
 *
 * This provider handles the registration of the AI generator services,
 * configuration publishing, and driver bindings within the Laravel
 * service container.
 *
 * @see \Darvis\LaravelAiGenerator\AiGenerator
 */
class AiGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the package services.
     *
     * Publishes the configuration file to the application's config directory.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/ai-generator.php' => config_path('ai-generator.php'),
        ], 'ai-generator-config');
    }

    /**
     * Register the package services.
     *
     * Binds the AI content driver and generator as singletons in the container.
     * The driver is resolved based on the configured driver name.
     *
     * @throws \RuntimeException  If an unsupported driver is configured
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/ai-generator.php',
            'ai-generator'
        );

        $this->app->singleton(AiContentDriver::class, function ($app) {
            $driver = config('ai-generator.driver', 'openai');

            return match ($driver) {
                'openai' => new OpenAiDriver(),
                default => throw new \RuntimeException("Unsupported AI driver: {$driver}"),
            };
        });

        $this->app->singleton(AiGenerator::class, function ($app) {
            return new AiGenerator(
                $app->make(AiContentDriver::class)
            );
        });

        $this->app->alias(AiGenerator::class, 'ai-generator');
    }
}
