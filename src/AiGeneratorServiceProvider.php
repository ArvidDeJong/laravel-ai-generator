<?php

namespace Darvis\LaravelAiGenerator;

use Darvis\LaravelAiGenerator\Console\InstallCommand;
use Darvis\LaravelAiGenerator\Console\StatusCommand;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\Mcp\AiGeneratorServer;
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;

/**
 * Laravel service provider for the AI Generator package.
 *
 * This provider handles the registration of the AI generator services,
 * configuration publishing, the Artisan commands, the optional MCP
 * server and the driver bindings within the Laravel service container.
 *
 * @see AiGenerator
 */
class AiGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the package services.
     *
     * Publishes the configuration file, registers the commands and, when
     * laravel/mcp is installed, the local MCP server.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/ai-generator.php' => config_path('ai-generator.php'),
        ], 'ai-generator-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                StatusCommand::class,
            ]);
        }

        // laravel/mcp is optional (it needs Laravel 12.41 or newer), so the server is only
        // registered when the host app has installed it. Local means STDIO through
        // "php artisan mcp:start", which needs shell access; a web server is up to the app.
        if (class_exists(Mcp::class) && AiGeneratorConfig::mcpEnabled()) {
            Mcp::local(AiGeneratorConfig::mcpHandle(), AiGeneratorServer::class);
        }
    }

    /**
     * Register the package services.
     *
     * Binds the driver manager, the default AI content driver and the
     * generator as singletons in the container.
     *
     * @throws \RuntimeException If an unsupported driver is configured
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/ai-generator.php',
            'ai-generator'
        );

        $this->app->singleton(AiGeneratorManager::class, fn ($app) => new AiGeneratorManager($app));

        $this->app->singleton(AiContentDriver::class, function ($app) {
            return $app->make(AiGeneratorManager::class)->driver();
        });

        $this->app->singleton(AiGenerator::class, function ($app) {
            return new AiGenerator(
                $app->make(AiContentDriver::class),
                $app->make(AiGeneratorManager::class),
            );
        });

        $this->app->alias(AiGenerator::class, 'ai-generator');
    }
}
