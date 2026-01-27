# Configuration

## Configuration File

After publishing, you'll find the configuration at `config/ai-generator.php`.

## Available Options

### Driver Selection

```php
'driver' => env('AI_GENERATOR_DRIVER', 'openai'),
```

Currently supported: `openai`

### Default Language

```php
'default_language' => env('AI_GENERATOR_LANGUAGE', 'nl'),
```

ISO 639-1 language codes: `nl`, `en`, `de`, `fr`, `es`, etc.

### Content Defaults

```php
'defaults' => [
    'tone' => env('AI_GENERATOR_TONE', 'informal'),
    'reading_level' => env('AI_GENERATOR_LEVEL', 'general'),
    'max_words' => env('AI_GENERATOR_MAX_WORDS', 900),
],
```

**Tone options:**
- `informal` - Casual, friendly writing
- `neutral` - Balanced, professional
- `formal` - Business, academic style

**Reading level options:**
- `simple` - Easy to understand, short sentences
- `general` - Standard readability
- `expert` - Technical, detailed content

### OpenAI Driver Settings

```php
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
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `OPENAI_API_KEY` | - | Your OpenAI API key (required) |
| `OPENAI_BASE_URL` | `https://api.openai.com/v1` | API endpoint URL |
| `OPENAI_MODEL` | `gpt-4.1-mini` | Model for text generation |
| `OPENAI_IMAGE_MODEL` | `gpt-image-1` | Model for image generation |
| `OPENAI_TEMPERATURE` | `0.7` | Creativity (0 = focused, 1 = creative) |
| `OPENAI_TIMEOUT` | `45` | Request timeout in seconds |
| `AI_GENERATOR_LANGUAGE` | `nl` | Default content language |
| `AI_GENERATOR_TONE` | `informal` | Default tone of voice |
| `AI_GENERATOR_LEVEL` | `general` | Default reading level |
| `AI_GENERATOR_MAX_WORDS` | `900` | Default maximum words |

## Example .env Configuration

```env
# Required
OPENAI_API_KEY=sk-your-api-key-here

# Optional - OpenAI settings
OPENAI_MODEL=gpt-4.1-mini
OPENAI_IMAGE_MODEL=gpt-image-1
OPENAI_TEMPERATURE=0.7
OPENAI_TIMEOUT=45

# Optional - Content defaults
AI_GENERATOR_LANGUAGE=en
AI_GENERATOR_TONE=neutral
AI_GENERATOR_LEVEL=general
AI_GENERATOR_MAX_WORDS=600
```

## Using Azure OpenAI

To use Azure OpenAI instead of OpenAI directly, update the base URL:

```env
OPENAI_BASE_URL=https://your-resource.openai.azure.com/openai/deployments/your-deployment
OPENAI_API_KEY=your-azure-api-key
```

## Runtime Configuration

You can also override settings at runtime:

```php
config(['ai-generator.defaults.tone' => 'formal']);

$result = $generator->generate(new ContentRequest(
    topic: 'Your topic',
));
```

Or pass options directly in the request (recommended):

```php
$result = $generator->generate(new ContentRequest(
    topic: 'Your topic',
    tone: 'formal',
    readingLevel: 'expert',
));
```
