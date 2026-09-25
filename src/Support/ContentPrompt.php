<?php

namespace Darvis\LaravelAiGenerator\Support;

use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;
use RuntimeException;

/**
 * The prompt, the JSON schema and the parsing of the answer, shared by every built-in driver so
 * that Claude, Gemini, Grok and OpenAI get the same instruction and fill the same fields.
 *
 * @internal
 */
final class ContentPrompt
{
    /**
     * The names of the fields the model has to return, in schema order.
     */
    public const FIELDS = ['title', 'intro', 'text', 'seo_title', 'seo_description', 'image_prompt'];

    /**
     * Build the generation prompt from the content request.
     */
    public static function build(ContentRequest $r): string
    {
        $keywords = $r->keywords ? implode(', ', $r->keywords) : '';
        $brand = $r->brand ? "Brand/organization: {$r->brand}\n" : '';
        $audience = $r->audience ? "Target audience: {$r->audience}\n" : '';
        $cta = $r->cta ? "CTA: {$r->cta}\n" : '';

        $tone = $r->tone ?? AiGeneratorConfig::defaultTone();
        $reading = $r->readingLevel ?? AiGeneratorConfig::defaultReadingLevel();
        $language = $r->language ?? AiGeneratorConfig::defaultLanguage();

        $maxWords = $r->maxWords ?? AiGeneratorConfig::defaultMaxWords();

        $imagePart = $r->includeImage
            ? "Also create an 'image_prompt' (in English) for a {$r->imageStyle} hero image in aspect {$r->imageAspect}. No text in the image.\n"
            : "No image_prompt needed.\n";

        $languageInstructions = self::languageInstructions($language, $tone);

        return <<<PROMPT
You are a content writer and SEO specialist.

Write content about the topic:
"{$r->topic}"

Requirements:
- Language: {$language}
- Tone of voice: {$tone} {$languageInstructions}
- Reading level: {$reading}
- Maximum approximately {$maxWords} words for the main text (excluding intro).
{$brand}{$audience}{$cta}
- Naturally incorporate these keywords (if provided): {$keywords}

Output:
- Return ONLY valid JSON according to the schema.
- Fields: title, intro, text, seo_title, seo_description, image_prompt
- intro: 2-4 sentences, engaging (plain text)
- text: Use HTML formatting. Use <h2> for subheadings, <p> for paragraphs, and <ul>/<li> for lists. No <h1> or markdown.
- seo_title: max 60 characters
- seo_description: max 155 characters
{$imagePart}
PROMPT;
    }

    /**
     * The JSON schema of the answer: six required strings and nothing else.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array_fill_keys(self::FIELDS, ['type' => 'string']),
            'required' => self::FIELDS,
        ];
    }

    /**
     * Decode the JSON text a model returned.
     *
     * Some models wrap JSON in a Markdown code fence even when asked not to; the fence is removed.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException When the text is not a JSON object
     */
    public static function decode(string $text, string $provider): array
    {
        $text = trim($text);

        if (preg_match('/\A```(?:json)?\s*(.*?)\s*```\z/s', $text, $fenced)) {
            $text = $fenced[1];
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException("{$provider} returned non-JSON output (unexpected).");
        }

        return $decoded;
    }

    /**
     * Turn the decoded answer into a result without an image.
     *
     * @param  array<string, mixed>  $json
     */
    public static function toResult(array $json, ContentRequest $request, ?string $driver = null, ?string $model = null): ContentResult
    {
        return new ContentResult(
            title: self::string($json, 'title'),
            intro: self::string($json, 'intro'),
            text: self::string($json, 'text'),
            seoTitle: self::string($json, 'seo_title'),
            seoDescription: self::string($json, 'seo_description'),
            imagePrompt: $request->includeImage ? self::string($json, 'image_prompt') : null,
            driver: $driver,
            model: $model,
        );
    }

    /**
     * Add the outcome of the image call to a text result.
     *
     * @param  array{url?: string|null, base64?: string|null, error?: string}  $image
     */
    public static function withImage(ContentResult $result, array $image): ContentResult
    {
        return new ContentResult(
            title: $result->title,
            intro: $result->intro,
            text: $result->text,
            seoTitle: $result->seoTitle,
            seoDescription: $result->seoDescription,
            imagePrompt: $result->imagePrompt,
            imageUrl: empty($image['error']) ? ($image['url'] ?? null) : null,
            imageBase64: empty($image['error']) ? ($image['base64'] ?? null) : null,
            errorMessage: empty($image['error']) ? null : (string) $image['error'],
            driver: $result->driver,
            model: $result->model,
        );
    }

    /**
     * Prefix the image prompt with the requested style.
     */
    public static function styledImagePrompt(string $prompt, string $style): string
    {
        $stylePrefix = match ($style) {
            'photo' => 'Professional high-quality photograph of',
            'illustration' => 'Digital illustration of',
            'flat' => 'Flat design vector illustration of',
            '3d' => '3D rendered image of',
            default => '',
        };

        return $stylePrefix ? "{$stylePrefix} {$prompt}. No text or watermarks." : $prompt;
    }

    /**
     * Pronoun guidance for languages that distinguish a formal and an informal "you".
     */
    private static function languageInstructions(string $language, string $tone): string
    {
        return match ($language) {
            'nl' => $tone === 'informal' ? '(use je/jij where appropriate)' : '',
            'de' => $tone === 'informal' ? '(use du where appropriate)' : '(use Sie)',
            'fr' => $tone === 'informal' ? '(use tu where appropriate)' : '(use vous)',
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private static function string(array $json, string $key): string
    {
        $value = $json[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
