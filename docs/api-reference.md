# API Reference

## Classes

### AiGenerator

The main service class for content generation.

**Namespace:** `Darvis\LaravelAiGenerator`

#### Methods

##### generate(ContentRequest $request): ContentResult

Generates AI-powered content based on the provided request.

```php
public function generate(ContentRequest $request): ContentResult
```

**Parameters:**
- `$request` - A `ContentRequest` instance with generation parameters

**Returns:** `ContentResult` - The generated content

**Throws:** `RuntimeException` - If the AI driver encounters an error

**Example:**
```php
$generator = app(AiGenerator::class);
$result = $generator->generate(new ContentRequest(topic: 'Your topic'));
```

---

### ContentRequest

Immutable data transfer object for content generation requests.

**Namespace:** `Darvis\LaravelAiGenerator`

#### Constructor

```php
public function __construct(
    string $topic,
    ?string $language = null,
    ?string $audience = null,
    ?string $tone = null,
    ?string $readingLevel = null,
    ?array $keywords = null,
    ?string $cta = null,
    ?string $brand = null,
    ?int $maxWords = null,
    bool $includeImage = true,
    ?string $imageStyle = 'photo',
    ?string $imageAspect = '16:9',
)
```

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$topic` | `string` | The main topic (required) |
| `$language` | `?string` | ISO 639-1 language code |
| `$audience` | `?string` | Target audience description |
| `$tone` | `?string` | Writing tone (informal/neutral/formal) |
| `$readingLevel` | `?string` | Complexity (simple/general/expert) |
| `$keywords` | `?array` | SEO keywords array |
| `$cta` | `?string` | Call-to-action text |
| `$brand` | `?string` | Brand/organization name |
| `$maxWords` | `?int` | Maximum word count |
| `$includeImage` | `bool` | Generate image prompt |
| `$imageStyle` | `?string` | Image style |
| `$imageAspect` | `?string` | Image aspect ratio |

---

### ContentResult

Immutable data transfer object containing generated content.

**Namespace:** `Darvis\LaravelAiGenerator`

#### Constructor

```php
public function __construct(
    string $title,
    string $intro,
    string $text,
    string $seoTitle,
    string $seoDescription,
    ?string $imagePrompt = null,
    ?string $imageUrl = null,
    ?string $imageBase64 = null,
    ?string $errorMessage = null,
)
```

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$title` | `string` | Generated title |
| `$intro` | `string` | 2-4 sentence introduction |
| `$text` | `string` | Main content (HTML) |
| `$seoTitle` | `string` | SEO title (max 60 chars) |
| `$seoDescription` | `string` | Meta description (max 155 chars) |
| `$imagePrompt` | `?string` | English image prompt |
| `$imageUrl` | `?string` | Generated image URL |
| `$imageBase64` | `?string` | Base64 image data |
| `$errorMessage` | `?string` | Error message if any |

#### Methods

##### hasError(): bool

Check if the generation encountered an error.

```php
public function hasError(): bool
```

**Returns:** `true` if an error message is present

##### hasImage(): bool

Check if image data is available.

```php
public function hasImage(): bool
```

**Returns:** `true` if `imageUrl` or `imageBase64` is present

---

### AiContentDriver (Interface)

Contract for AI content generation drivers.

**Namespace:** `Darvis\LaravelAiGenerator\Contracts`

#### Methods

##### generate(ContentRequest $request): ContentResult

Generate content based on the request.

```php
public function generate(ContentRequest $request): ContentResult;
```

---

### AiGenerator (Facade)

Static facade for the AiGenerator service.

**Namespace:** `Darvis\LaravelAiGenerator\Facades`

#### Methods

##### generate(ContentRequest $request): ContentResult

```php
AiGenerator::generate(new ContentRequest(topic: 'Your topic'));
```

---

## Configuration

### Config File: `config/ai-generator.php`

```php
return [
    'driver' => env('AI_GENERATOR_DRIVER', 'openai'),
    'default_language' => env('AI_GENERATOR_LANGUAGE', 'nl'),

    'defaults' => [
        'tone' => env('AI_GENERATOR_TONE', 'informal'),
        'reading_level' => env('AI_GENERATOR_LEVEL', 'general'),
        'max_words' => env('AI_GENERATOR_MAX_WORDS', 900),
    ],

    'drivers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
            'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
            'timeout' => env('OPENAI_TIMEOUT', 45),
        ],
    ],
];
```

---

## Exceptions

### RuntimeException

Thrown when:
- `OPENAI_API_KEY` is not configured
- API connection fails
- API returns an error response
- Response cannot be parsed

**Example handling:**
```php
try {
    $result = $generator->generate($request);
} catch (RuntimeException $e) {
    Log::error('AI generation failed: ' . $e->getMessage());
}
```
