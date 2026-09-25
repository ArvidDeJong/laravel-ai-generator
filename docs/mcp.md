---
title: "MCP server"
nav_order: 7
description: "Let an AI assistant such as Claude Code generate content and images through your Laravel app with the MCP server of the package, and what to secure before you expose it."
---

# MCP server

**MCP**, the Model Context Protocol, is a standard way for an AI assistant to use tools of another
program. Claude Code, Claude Desktop, Cursor and other assistants can connect to an MCP server and
call its tools. This package offers an MCP server with tools that generate content and images through
your Laravel application, with the providers and keys you set up there.

You don't need MCP to use the package. It is an extra.

## What you need

- Laravel 12.41 or newer.
- The official `laravel/mcp` package:

```bash
composer require laravel/mcp
```

That is all. When `laravel/mcp` is installed, this package registers a **local** MCP server named
`ai-generator` by itself.

## Connect Claude Code

In the root folder of your application:

```bash
claude mcp add ai-generator -- php artisan mcp:start ai-generator
```

Claude Code now starts the server when it needs it. Ask it, for example, "Write an article about
winter tyres with the ai-generator tool". Other assistants take the same command,
`php artisan mcp:start ai-generator`, in their MCP settings.

A local server runs on your own machine and talks to the assistant through the command line. Nobody
on the internet can reach it.

## The tools

| Tool | What it does | Billed |
| --- | --- | --- |
| `list-providers` | Shows which providers have a key, their models, the default driver, the image driver and the fallbacks. Never shows a key. | no |
| `generate-content` | Writes a title, intro, HTML text, SEO title and meta description, and optionally an image. | yes |
| `generate-image` | Makes one image from a prompt with OpenAI, Gemini or Grok. | yes |

`generate-content` takes the same options as a `ContentRequest`, in snake case: `topic` (required),
`language`, `audience`, `tone`, `reading_level`, `keywords`, `cta`, `brand`, `max_words`,
`include_image`, `image_style`, `image_aspect`, plus `driver` and `model` to pick a provider.

**`include_image` is `false` by default in the MCP tool**, unlike in PHP. An assistant should not
start a second billed call unless you ask for an image.

`generate-image` takes `prompt` (required), `style`, `aspect` and `driver`.

Every generation is a billed call to the provider, also when an assistant starts it. Set a spending
limit at your provider; see [Get your API keys](api-keys.md).

## Settings

| Environment variable | Default | What it does |
| --- | --- | --- |
| `AI_GENERATOR_MCP` | `true` | Set to `false` to not register the local server. |
| `AI_GENERATOR_MCP_HANDLE` | `ai-generator` | The name for `php artisan mcp:start`. |

`php artisan ai-generator:status` shows whether the server is available.

## A web server: only behind authentication

The package does not register an MCP server on the web, on purpose. A web server can be called by
anyone who finds the URL, and every call spends your credits. When you need one, register it
yourself in `routes/ai.php` and put it behind authentication:

**`routes/ai.php`**

```php
use Darvis\LaravelAiGenerator\Mcp\AiGeneratorServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/ai-generator', AiGeneratorServer::class)
    ->middleware('auth:sanctum');
```

`php artisan vendor:publish --tag=ai-routes` creates `routes/ai.php` when you don't have it yet.
Never leave out the middleware: without it, anyone on the internet can generate content on your
account.
