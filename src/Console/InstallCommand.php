<?php

namespace Darvis\LaravelAiGenerator\Console;

use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Darvis\LaravelAiGenerator\Support\EnvFile;
use Darvis\LaravelAiGenerator\Support\ModelCatalog;
use Darvis\LaravelAiGenerator\Support\Provider;
use Illuminate\Console\Command;
use Laravel\Mcp\Facades\Mcp;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * The install wizard: pick one or more providers, check each API key against the provider, choose
 * the models from the list the key may use, choose which provider writes, which one draws and which
 * ones take over on a failure, and write it all to .env.
 */
final class InstallCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ai-generator:install';

    /**
     * @var string
     */
    protected $description = 'Set up the AI providers (Claude, ChatGPT, Gemini, Grok) for darvis/laravel-ai-generator';

    private EnvFile $env;

    /**
     * The environment variables to write, in the order they were chosen.
     *
     * @var array<string, string|null>
     */
    private array $values = [];

    public function handle(): int
    {
        $this->env = new EnvFile($this->laravel->environmentFilePath());

        intro('AI Generator setup');

        note(implode("\n", [
            'You can use more than one provider: one writes the text, one makes the images,',
            'and others take over when the first one fails. Every provider bills its own calls.',
        ]));

        $providers = $this->chooseProviders();

        if ($providers === []) {
            warning('No provider was set up. Nothing was written.');

            return self::FAILURE;
        }

        $default = $this->chooseDefaultDriver($providers);
        $this->chooseImageDriver($providers, $default);
        $this->chooseFallbacks($providers, $default);
        $this->chooseContentDefaults();

        $this->summary();

        if (! confirm('Write these settings to .env?', default: true)) {
            warning('Nothing was written.');

            return self::FAILURE;
        }

        try {
            $this->env->set($this->values);
        } catch (RuntimeException $e) {
            error($e->getMessage());

            return self::FAILURE;
        }

        info('Written to '.$this->laravel->environmentFilePath().'.');

        if ($this->laravel->configurationIsCached()) {
            warning('The config is cached. Run "php artisan config:cache" (or config:clear) to use the new settings.');
        }

        $this->mcpNote();

        outro('Done. Run "php artisan ai-generator:status" at any time to check the keys again.');

        return self::SUCCESS;
    }

    /**
     * Ask which providers to use and set up every one of them. A provider whose key cannot be
     * checked and that the user skips is left out.
     *
     * @return list<Provider>
     */
    private function chooseProviders(): array
    {
        $options = [];
        $preselected = [];

        foreach (Provider::cases() as $provider) {
            $options[$provider->value] = $provider->label().($provider->supportsImages() ? ', text and images' : ', text only');

            if ($this->env->get($provider->apiKeyEnv()) || AiGeneratorConfig::apiKey($provider) !== null) {
                $preselected[] = $provider->value;
            }
        }

        /** @var list<string> $chosen */
        $chosen = multiselect(
            label: 'Which AI providers do you have an API key for?',
            options: $options,
            default: $preselected,
            required: true,
            hint: 'Space selects, Enter confirms. Pick every provider you want to use.',
        );

        $providers = [];

        foreach ($chosen as $name) {
            $provider = Provider::from($name);

            if ($this->setUpProvider($provider)) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Ask for the key, check it and choose the models. False when the user skips the provider.
     */
    private function setUpProvider(Provider $provider): bool
    {
        info($provider->label());

        $key = $this->askApiKey($provider);

        while (true) {
            try {
                $models = spin(
                    fn () => ModelCatalog::fetch($provider, $key),
                    "Checking the key with {$provider->shortName()}...",
                );

                break;
            } catch (RuntimeException $e) {
                error($e->getMessage());

                $next = select(
                    label: 'What now?',
                    options: [
                        'retry' => 'Enter another key',
                        'keep' => 'Keep this key without checking it',
                        'skip' => "Skip {$provider->shortName()}",
                    ],
                    default: 'retry',
                );

                if ($next === 'skip') {
                    return false;
                }

                if ($next === 'keep') {
                    $models = ['text' => [], 'image' => []];

                    break;
                }

                $key = $this->askApiKey($provider, askToKeep: false);
            }
        }

        $prefix = $provider->envPrefix();

        // A key that comes from the server environment rather than .env stays where it is.
        if ($this->env->get($provider->apiKeyEnv()) !== null || $key !== AiGeneratorConfig::apiKey($provider)) {
            $this->values[$provider->apiKeyEnv()] = $key;
        }

        $this->values["{$prefix}_MODEL"] = $this->chooseModel(
            "Which {$provider->shortName()} model writes the text?",
            $models['text'],
            AiGeneratorConfig::model($provider),
        );

        if ($provider->supportsImages()) {
            $this->values["{$prefix}_IMAGE_MODEL"] = $this->chooseModel(
                "Which {$provider->shortName()} model makes images?",
                $models['image'],
                (string) AiGeneratorConfig::imageModel($provider),
            );
        }

        return true;
    }

    private function askApiKey(Provider $provider, bool $askToKeep = true): string
    {
        $existing = $this->env->get($provider->apiKeyEnv()) ?: AiGeneratorConfig::apiKey($provider);

        if ($askToKeep && $existing && confirm("Keep the {$provider->apiKeyEnv()} that is already set (".self::mask($existing).')?', default: true)) {
            return $existing;
        }

        return trim(password(
            label: "{$provider->apiKeyEnv()}",
            required: true,
            hint: "Create a key at {$provider->apiKeyUrl()}",
        ));
    }

    /**
     * Choose a model from the list the key may use, or type one when the list is empty.
     *
     * @param  list<string>  $available
     */
    private function chooseModel(string $label, array $available, string $current): string
    {
        if ($available === []) {
            return trim(text(label: $label, default: $current, required: true));
        }

        $other = '__other__';
        $options = array_values(array_unique([$current, ...$available]));
        $options = array_combine($options, array_map(
            fn (string $model) => $model === $current ? "{$model} (current)" : $model,
            $options,
        ));
        $options[$other] = 'Another model, type its id';

        $model = (string) select(label: $label, options: $options, default: $current, scroll: 10);

        return $model === $other ? trim(text(label: 'Model id', required: true)) : $model;
    }

    /**
     * @param  list<Provider>  $providers
     */
    private function chooseDefaultDriver(array $providers): Provider
    {
        $current = Provider::fromName(AiGeneratorConfig::driver());
        $default = in_array($current, $providers, true) ? $current : $providers[0];

        if (count($providers) > 1) {
            $default = Provider::from((string) select(
                label: 'Which provider writes the text by default?',
                options: self::labels($providers),
                default: $default->value,
                hint: 'Per call you can pick another one with AiGenerator::using().',
            ));
        }

        $this->values['AI_GENERATOR_DRIVER'] = $default->value;

        return $default;
    }

    /**
     * @param  list<Provider>  $providers
     */
    private function chooseImageDriver(array $providers, Provider $default): void
    {
        $capable = array_values(array_filter($providers, fn (Provider $provider) => $provider->supportsImages()));

        if ($capable === []) {
            warning(implode("\n", [
                "{$default->shortName()} cannot make images, and none of the providers you chose can.",
                'Pass includeImage: false in every request, or run this wizard again and add OpenAI, Gemini or Grok.',
            ]));
            $this->values['AI_GENERATOR_IMAGE_DRIVER'] = null;

            return;
        }

        $image = in_array($default, $capable, true) ? $default : $capable[0];

        if (count($capable) > 1) {
            $image = Provider::from((string) select(
                label: 'Which provider makes the images?',
                options: self::labels($capable),
                default: $image->value,
            ));
        } elseif ($image !== $default) {
            info("{$default->shortName()} cannot make images; {$image->shortName()} will make them.");
        }

        $this->values['AI_GENERATOR_IMAGE_DRIVER'] = $image->value;
    }

    /**
     * @param  list<Provider>  $providers
     */
    private function chooseFallbacks(array $providers, Provider $default): void
    {
        $others = array_values(array_filter($providers, fn (Provider $provider) => $provider !== $default));

        if ($others === []) {
            $this->values['AI_GENERATOR_FALLBACKS'] = null;

            return;
        }

        /** @var list<string> $fallbacks */
        $fallbacks = multiselect(
            label: "When {$default->shortName()} fails, which providers should take over?",
            options: self::labels($others),
            default: [],
            hint: 'They are tried in this order. Select none to report the failure instead.',
        );

        $this->values['AI_GENERATOR_FALLBACKS'] = $fallbacks === [] ? null : implode(',', $fallbacks);
    }

    private function chooseContentDefaults(): void
    {
        if (! confirm('Change the default language, tone and reading level?', default: false)) {
            return;
        }

        $this->values['AI_GENERATOR_LANGUAGE'] = trim(text(
            label: 'Default language (ISO 639-1 code)',
            default: AiGeneratorConfig::defaultLanguage(),
            required: true,
            validate: fn (string $value) => preg_match('/^[a-z]{2}$/', trim($value)) ? null : 'Use a two letter code, such as en or nl.',
        ));

        $this->values['AI_GENERATOR_TONE'] = (string) select(
            label: 'Default tone',
            options: ['informal' => 'Informal', 'neutral' => 'Neutral', 'formal' => 'Formal'],
            default: AiGeneratorConfig::defaultTone(),
        );

        $this->values['AI_GENERATOR_LEVEL'] = (string) select(
            label: 'Default reading level',
            options: ['simple' => 'Simple', 'general' => 'General', 'expert' => 'Expert'],
            default: AiGeneratorConfig::defaultReadingLevel(),
        );
    }

    private function summary(): void
    {
        $rows = [];

        foreach ($this->values as $key => $value) {
            $shown = $value === null || $value === '' ? '(empty)' : $value;
            $rows[] = [$key, str_ends_with($key, '_API_KEY') ? self::mask((string) $value) : $shown];
        }

        table(['Variable', 'Value'], $rows);
    }

    private function mcpNote(): void
    {
        $handle = AiGeneratorConfig::mcpHandle();

        if (class_exists(Mcp::class)) {
            note(implode("\n", [
                "MCP: this app offers the local MCP server \"{$handle}\". Add it to Claude Code with:",
                "  claude mcp add {$handle} -- php artisan mcp:start {$handle}",
            ]));

            return;
        }

        note(implode("\n", [
            'MCP: to let an assistant such as Claude Code generate content through this app,',
            'install laravel/mcp (Laravel 12.41 or newer): composer require laravel/mcp',
        ]));
    }

    /**
     * @param  list<Provider>  $providers
     * @return array<string, string>
     */
    private static function labels(array $providers): array
    {
        $labels = [];

        foreach ($providers as $provider) {
            $labels[$provider->value] = $provider->label();
        }

        return $labels;
    }

    private static function mask(string $key): string
    {
        return strlen($key) <= 8 ? str_repeat('*', strlen($key)) : substr($key, 0, 3).'...'.substr($key, -4);
    }
}
