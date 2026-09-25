<?php

use Darvis\LaravelAiGenerator\Mcp\AiGeneratorServer;
use Darvis\LaravelAiGenerator\Mcp\Tools\GenerateContentTool;
use Darvis\LaravelAiGenerator\Mcp\Tools\GenerateImageTool;
use Darvis\LaravelAiGenerator\Mcp\Tools\ListProvidersTool;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Registrar;

/**
 * laravel/mcp is optional and needs Laravel 12.41 or newer, so it is not a dev dependency: the
 * CI matrix also installs Laravel 11. Install it locally to run these tests.
 */
beforeEach(function () {
    if (! class_exists(Server::class)) {
        $this->markTestSkipped('laravel/mcp is not installed.');
    }

    Http::preventStrayRequests();
});

test('list-providers shows which providers have a key and never the key itself', function () {
    AiGeneratorServer::tool(ListProvidersTool::class)
        ->assertOk()
        ->assertSee('"has_api_key"')
        ->assertSee('claude-sonnet-5')
        ->assertDontSee('test-api-key');
});

test('generate-content writes with the driver it is given and makes no image by default', function () {
    config(['ai-generator.drivers.anthropic.api_key' => 'sk-ant']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
        'title' => 'Lighthouses', 'intro' => 'I', 'text' => '<p>T</p>', 'seo_title' => 'S', 'seo_description' => 'D', 'image_prompt' => '',
    ])]]])]);

    AiGeneratorServer::tool(GenerateContentTool::class, ['topic' => 'Lighthouses', 'driver' => 'claude'])
        ->assertOk()
        ->assertSee('Lighthouses')
        ->assertSee('anthropic');

    Http::assertSentCount(1);
});

test('generate-content reports a failure as a tool error', function () {
    config(['ai-generator.drivers.gemini.api_key' => null]);

    AiGeneratorServer::tool(GenerateContentTool::class, ['topic' => 'x', 'driver' => 'gemini'])
        ->assertHasErrors(['GEMINI_API_KEY is not set.']);
});

test('generate-image returns the image', function () {
    Http::fake(['api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode("\x89PNG....")]]])]);

    AiGeneratorServer::tool(GenerateImageTool::class, ['prompt' => 'a lighthouse'])
        ->assertOk();
});

test('the local server is registered under its handle', function () {
    expect(app(Registrar::class)->getLocalServer('ai-generator'))->not->toBeNull();
});
