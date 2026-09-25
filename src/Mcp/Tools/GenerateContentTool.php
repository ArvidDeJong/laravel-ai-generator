<?php

namespace Darvis\LaravelAiGenerator\Mcp\Tools;

use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Mcp\ImageResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use RuntimeException;

#[Description('Writes web content about a topic: a title, an intro, the main text as HTML, an SEO title and a meta description, and optionally an image. One billed call to the AI provider, two with an image.')]
#[IsOpenWorld]
class GenerateContentTool extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()->description('What the text is about.')->required(),
            'language' => $schema->string()->description('ISO 639-1 code such as en or nl. Defaults to the configured language.'),
            'audience' => $schema->string()->description('Who the text is for.'),
            'tone' => $schema->string()->enum(['informal', 'neutral', 'formal']),
            'reading_level' => $schema->string()->enum(['simple', 'general', 'expert']),
            'keywords' => $schema->array()->items($schema->string())->description('SEO keywords to work in.'),
            'cta' => $schema->string()->description('A call to action to include.'),
            'brand' => $schema->string()->description('The brand or organisation the text is for.'),
            'max_words' => $schema->integer()->min(50)->max(3000)->description('About how many words the main text may have.'),
            'include_image' => $schema->boolean()->default(false)->description('Also generate an image, a second billed call.'),
            'image_style' => $schema->string()->enum(['photo', 'illustration', 'flat', '3d']),
            'image_aspect' => $schema->string()->enum(['1:1', '4:5', '16:9']),
            'driver' => $schema->string()->description('openai, anthropic, gemini or xai. Defaults to the configured driver.'),
            'model' => $schema->string()->description('A model id of that driver. Defaults to the configured model.'),
        ];
    }

    /**
     * @return Response|list<Response>
     */
    public function handle(Request $request, AiGenerator $generator): Response|array
    {
        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:1000'],
            'language' => ['nullable', 'string', 'size:2'],
            'audience' => ['nullable', 'string', 'max:500'],
            'tone' => ['nullable', 'in:informal,neutral,formal'],
            'reading_level' => ['nullable', 'in:simple,general,expert'],
            'keywords' => ['nullable', 'array', 'max:20'],
            'keywords.*' => ['string', 'max:100'],
            'cta' => ['nullable', 'string', 'max:500'],
            'brand' => ['nullable', 'string', 'max:200'],
            'max_words' => ['nullable', 'integer', 'min:50', 'max:3000'],
            'include_image' => ['nullable', 'boolean'],
            'image_style' => ['nullable', 'in:photo,illustration,flat,3d'],
            'image_aspect' => ['nullable', 'in:1:1,4:5,16:9'],
            'driver' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);

        $contentRequest = new ContentRequest(
            topic: $validated['topic'],
            language: $validated['language'] ?? null,
            audience: $validated['audience'] ?? null,
            tone: $validated['tone'] ?? null,
            readingLevel: $validated['reading_level'] ?? null,
            keywords: $validated['keywords'] ?? null,
            cta: $validated['cta'] ?? null,
            brand: $validated['brand'] ?? null,
            maxWords: $validated['max_words'] ?? null,
            includeImage: (bool) ($validated['include_image'] ?? false),
            imageStyle: $validated['image_style'] ?? 'photo',
            imageAspect: $validated['image_aspect'] ?? '16:9',
        );

        try {
            $driver = $validated['driver'] ?? null;
            $result = ($driver !== null ? $generator->using($driver, $validated['model'] ?? null) : $generator)
                ->generate($contentRequest);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $responses = [Response::json([
            'title' => $result->title,
            'intro' => $result->intro,
            'text' => $result->text,
            'seo_title' => $result->seoTitle,
            'seo_description' => $result->seoDescription,
            'image_prompt' => $result->imagePrompt,
            'image_url' => $result->imageUrl,
            'error' => $result->errorMessage,
            'driver' => $result->driver,
            'model' => $result->model,
        ])];

        if ($result->imageBase64 !== null) {
            $responses[] = ImageResponse::fromBase64($result->imageBase64);
        }

        return $responses;
    }
}
