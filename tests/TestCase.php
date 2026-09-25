<?php

namespace Darvis\LaravelAiGenerator\Tests;

use Darvis\LaravelAiGenerator\AiGeneratorServiceProvider;
use Illuminate\Foundation\Application;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for the Laravel AI Generator package.
 *
 * This class provides the foundation for all feature tests,
 * setting up the Laravel testing environment with the package
 * service provider and default configuration.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get package providers.
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        // laravel/mcp is optional and not a dev dependency (it needs Laravel 12.41+); when it is
        // installed locally, its provider is loaded so the MCP tests can run.
        return array_values(array_filter([
            class_exists(McpServiceProvider::class) ? McpServiceProvider::class : null,
            AiGeneratorServiceProvider::class,
        ]));
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai-generator.driver', 'openai');
        $app['config']->set('ai-generator.drivers.openai.api_key', 'test-api-key');
    }
}
