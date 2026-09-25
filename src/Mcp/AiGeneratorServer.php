<?php

namespace Darvis\LaravelAiGenerator\Mcp;

use Darvis\LaravelAiGenerator\Mcp\Tools\GenerateContentTool;
use Darvis\LaravelAiGenerator\Mcp\Tools\GenerateImageTool;
use Darvis\LaravelAiGenerator\Mcp\Tools\ListProvidersTool;
use Laravel\Mcp\Server;

/**
 * An MCP server that lets an assistant generate content and images through this application, with
 * the providers and keys configured here. Needs laravel/mcp; the service provider registers it as
 * a local server, "php artisan mcp:start ai-generator".
 */
class AiGeneratorServer extends Server
{
    protected string $name = 'AI Generator';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Generates web content (title, intro, HTML text, SEO title, meta description) and images with
        the AI providers configured in this Laravel application: Claude, ChatGPT, Gemini or Grok.
        Call list-providers first to see which providers have a key and which models they use.
        Every generation is a billed call to the provider; ask for an image only when one is needed.
        MARKDOWN;

    /**
     * @var array<int, class-string<Server\Tool>>
     */
    protected array $tools = [
        ListProvidersTool::class,
        GenerateContentTool::class,
        GenerateImageTool::class,
    ];
}
