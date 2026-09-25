<?php

namespace Darvis\LaravelAiGenerator\Mcp\Tools;

use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\Mcp\ImageResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Generates one image from a prompt with OpenAI, Gemini or Grok. One billed call to the AI provider. Claude cannot generate images.')]
#[IsOpenWorld]
class GenerateImageTool extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'prompt' => $schema->string()->description('What the image shows, preferably in English.')->required(),
            'style' => $schema->string()->enum(['photo', 'illustration', 'flat', '3d'])->default('photo'),
            'aspect' => $schema->string()->enum(['1:1', '4:5', '16:9'])->default('16:9'),
            'driver' => $schema->string()->description('openai, gemini or xai. Defaults to the configured image driver.'),
        ];
    }

    public function handle(Request $request, AiGenerator $generator): Response
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'style' => ['nullable', 'in:photo,illustration,flat,3d'],
            'aspect' => ['nullable', 'in:1:1,4:5,16:9'],
            'driver' => ['nullable', 'string', 'max:50'],
        ]);

        $image = $generator->generateImage(
            $validated['prompt'],
            $validated['style'] ?? 'photo',
            $validated['aspect'] ?? '16:9',
            $validated['driver'] ?? null,
        );

        if (! empty($image['error'])) {
            return Response::error($image['error']);
        }

        if (! empty($image['base64'])) {
            return ImageResponse::fromBase64($image['base64']);
        }

        if (! empty($image['url'])) {
            return Response::text($image['url']);
        }

        return Response::error('The provider returned no image.');
    }
}
