# Basic Usage

## Getting the Generator

### Via Dependency Injection (Recommended)

```php
use Darvis\LaravelAiGenerator\AiGenerator;

class ArticleController extends Controller
{
    public function __construct(
        private AiGenerator $generator
    ) {}

    public function generate()
    {
        $result = $this->generator->generate(new ContentRequest(
            topic: 'Your topic here',
        ));
    }
}
```

### Via Service Container

```php
use Darvis\LaravelAiGenerator\AiGenerator;

$generator = app(AiGenerator::class);
```

### Via Facade

```php
use Darvis\LaravelAiGenerator\Facades\AiGenerator;

$result = AiGenerator::generate(new ContentRequest(
    topic: 'Your topic here',
));
```

## Creating a Content Request

### Minimal Request

```php
use Darvis\LaravelAiGenerator\ContentRequest;

$request = new ContentRequest(
    topic: 'The benefits of remote work',
);
```

### Full Request with All Options

```php
$request = new ContentRequest(
    topic: 'Sustainable packaging solutions for e-commerce',
    language: 'en',
    audience: 'e-commerce business owners',
    tone: 'formal',
    readingLevel: 'expert',
    keywords: ['sustainable', 'eco-friendly', 'packaging', 'shipping'],
    cta: 'Contact us for a free consultation',
    brand: 'EcoPack Solutions',
    maxWords: 800,
    includeImage: true,
    imageStyle: 'photo',
    imageAspect: '16:9',
);
```

## Request Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `topic` | string | required | The main subject for content |
| `language` | string | `nl` | ISO 639-1 code (nl, en, de, fr...) |
| `audience` | string | null | Target audience description |
| `tone` | string | `informal` | informal, neutral, or formal |
| `readingLevel` | string | `general` | simple, general, or expert |
| `keywords` | array | null | SEO keywords to include |
| `cta` | string | null | Call-to-action text |
| `brand` | string | null | Brand/company name |
| `maxWords` | int | `900` | Maximum words for main text |
| `includeImage` | bool | `true` | Generate image prompt |
| `imageStyle` | string | `photo` | photo, illustration, flat, 3d |
| `imageAspect` | string | `16:9` | 1:1, 4:5, 16:9 |

## Working with Results

### The ContentResult Object

```php
$result = $generator->generate($request);

// Content fields
$result->title;           // "Why Sustainable Packaging Matters"
$result->intro;           // "In today's eco-conscious market..."
$result->text;            // "<h2>Introduction</h2><p>..."
$result->seoTitle;        // "Sustainable Packaging Guide | EcoPack"
$result->seoDescription;  // "Discover how sustainable packaging..."

// Image fields
$result->imagePrompt;     // "Professional photo of eco-friendly..."
$result->imageUrl;        // "https://..." (if generated)
$result->imageBase64;     // Base64 encoded image data

// Error handling
$result->errorMessage;    // Error message if something failed
```

### Helper Methods

```php
// Check for errors
if ($result->hasError()) {
    Log::error('Generation failed: ' . $result->errorMessage);
    return;
}

// Check if image was generated
if ($result->hasImage()) {
    // Save or display the image
    $imageData = $result->imageBase64 ?? file_get_contents($result->imageUrl);
}
```

## Practical Examples

### Blog Post Generation

```php
$result = $generator->generate(new ContentRequest(
    topic: 'How to start a vegetable garden',
    language: 'en',
    audience: 'beginner gardeners',
    tone: 'informal',
    readingLevel: 'simple',
    keywords: ['vegetable garden', 'gardening tips', 'grow vegetables'],
    maxWords: 1200,
));

$post = Post::create([
    'title' => $result->title,
    'excerpt' => $result->intro,
    'content' => $result->text,
    'meta_title' => $result->seoTitle,
    'meta_description' => $result->seoDescription,
]);
```

### Product Description

```php
$result = $generator->generate(new ContentRequest(
    topic: "Premium wireless headphones with noise cancellation",
    language: 'en',
    audience: 'tech enthusiasts',
    tone: 'neutral',
    keywords: ['wireless headphones', 'noise cancellation', 'premium audio'],
    brand: 'AudioTech Pro',
    cta: 'Order now and get free shipping',
    maxWords: 300,
    includeImage: false,
));
```

### Multi-language Content

```php
$languages = ['en', 'nl', 'de', 'fr'];
$results = [];

foreach ($languages as $lang) {
    $results[$lang] = $generator->generate(new ContentRequest(
        topic: 'Company sustainability report 2024',
        language: $lang,
        tone: 'formal',
        brand: 'GreenCorp International',
    ));
}
```

## Error Handling

```php
use RuntimeException;

try {
    $result = $generator->generate(new ContentRequest(
        topic: 'Your topic',
    ));

    if ($result->hasError()) {
        // Partial failure (e.g., text OK but image failed)
        Log::warning('Partial generation error: ' . $result->errorMessage);
    }

} catch (RuntimeException $e) {
    // Complete failure (e.g., API key invalid, connection failed)
    Log::error('Generation failed: ' . $e->getMessage());
}
```

## Using in Queued Jobs

For better performance, generate content in background jobs:

```php
class GenerateArticleContent implements ShouldQueue
{
    public function __construct(
        public Article $article,
        public string $topic,
    ) {}

    public function handle(AiGenerator $generator): void
    {
        $result = $generator->generate(new ContentRequest(
            topic: $this->topic,
        ));

        $this->article->update([
            'title' => $result->title,
            'content' => $result->text,
            'status' => 'generated',
        ]);
    }
}

// Dispatch the job
GenerateArticleContent::dispatch($article, 'Your topic');
```
