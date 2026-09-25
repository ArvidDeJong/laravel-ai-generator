<?php

namespace Darvis\LaravelAiGenerator\Console;

use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\AiGeneratorManager;
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\Drivers\Driver;
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Darvis\LaravelAiGenerator\Support\ModelCatalog;
use Darvis\LaravelAiGenerator\Support\Provider;
use Illuminate\Console\Command;
use Laravel\Mcp\Facades\Mcp;
use RuntimeException;

/**
 * Shows which providers are set up and checks every API key against its provider. The check asks
 * for the model list, which costs nothing; --test generates one short text, which is billed.
 */
final class StatusCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ai-generator:status
        {--offline : Do not contact the providers}
        {--test= : Generate one short text with this driver (one billed call, no image)}';

    /**
     * @var string
     */
    protected $description = 'Show the AI providers of darvis/laravel-ai-generator and check their API keys';

    public function handle(AiGeneratorManager $manager): int
    {
        $rows = [];
        $failed = false;

        foreach (Provider::cases() as $provider) {
            $key = AiGeneratorConfig::apiKey($provider);
            $check = '-';

            if ($key === null) {
                $check = 'no key';
            } elseif (! $this->option('offline')) {
                try {
                    $models = ModelCatalog::fetch($provider, $key);
                    $model = AiGeneratorConfig::model($provider);
                    $check = in_array($model, $models['text'], true) || $models['text'] === []
                        ? 'OK'
                        : "key OK, but {$model} is not in the model list";
                } catch (RuntimeException $e) {
                    $check = $e->getMessage();
                    $failed = true;
                }
            }

            $rows[] = [
                $provider->value,
                $key === null ? 'not set' : 'set',
                AiGeneratorConfig::model($provider),
                AiGeneratorConfig::imageModel($provider) ?? 'no images',
                $check,
            ];
        }

        $this->table(['Driver', 'API key', 'Text model', 'Image model', 'Check'], $rows);

        $default = $manager->normalize(AiGeneratorConfig::driver());
        $fallbacks = AiGeneratorConfig::fallbacks();

        try {
            $image = $manager->imageDriverFor($manager->driver());
            $imageName = $image instanceof Driver ? $image->provider()->value : ($image === null ? 'none' : $image::class);
        } catch (RuntimeException $e) {
            $imageName = $e->getMessage();
        }

        $this->components->twoColumnDetail('Default driver', $default);
        $this->components->twoColumnDetail('Image driver', $imageName);
        $this->components->twoColumnDetail('Fallbacks', $fallbacks === [] ? 'none' : implode(', ', $fallbacks));
        $this->components->twoColumnDetail('MCP server', class_exists(Mcp::class)
            ? (AiGeneratorConfig::mcpEnabled() ? 'php artisan mcp:start '.AiGeneratorConfig::mcpHandle() : 'disabled')
            : 'laravel/mcp is not installed');

        $test = $this->option('test');

        if (is_string($test) && $test !== '') {
            return $this->test($test) && ! $failed ? self::SUCCESS : self::FAILURE;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function test(string $driver): bool
    {
        $this->components->info("Generating a short test text with {$driver}...");

        try {
            $result = app(AiGenerator::class)->using($driver)->generate(new ContentRequest(
                topic: 'A short test text about the weather',
                language: 'en',
                maxWords: 60,
                includeImage: false,
            ));
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return false;
        }

        $this->components->twoColumnDetail('Model', (string) $result->model);
        $this->components->twoColumnDetail('Title', $result->title);
        $this->line('  '.$result->intro);

        return true;
    }
}
