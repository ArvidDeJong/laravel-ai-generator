<?php

namespace Darvis\LaravelAiGenerator;

use Closure;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;
use Darvis\LaravelAiGenerator\Contracts\AiImageDriver;
use Darvis\LaravelAiGenerator\Drivers\AnthropicDriver;
use Darvis\LaravelAiGenerator\Drivers\GeminiDriver;
use Darvis\LaravelAiGenerator\Drivers\OpenAiDriver;
use Darvis\LaravelAiGenerator\Drivers\XaiDriver;
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Darvis\LaravelAiGenerator\Support\Provider;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Builds the drivers by name, so one application can write with Claude and draw with OpenAI, or
 * switch provider per call.
 *
 * Driver names are those in config/ai-generator.php: openai, anthropic, gemini and xai. The names
 * chatgpt, claude and grok work as aliases. Register a driver of your own with extend().
 */
final class AiGeneratorManager
{
    /**
     * @var array<string, AiContentDriver>
     */
    private array $drivers = [];

    /**
     * @var array<string, Closure(Container, ?string): AiContentDriver>
     */
    private array $customCreators = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * The driver with this name, or the default driver. The instance is reused.
     *
     * @throws RuntimeException When no driver has this name
     */
    public function driver(?string $name = null): AiContentDriver
    {
        $name = $this->normalize($name ?? AiGeneratorConfig::driver());

        return $this->drivers[$name] ??= $this->build($name);
    }

    /**
     * A new instance of the driver with this name, optionally with another text model.
     *
     * @throws RuntimeException When no driver has this name
     */
    public function build(string $name, ?string $model = null): AiContentDriver
    {
        $name = $this->normalize($name);

        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->container, $model);
        }

        $images = fn (AiContentDriver $textDriver): ?AiImageDriver => $this->imageDriverFor($textDriver);

        return match ($name) {
            'anthropic' => new AnthropicDriver($model, $images),
            'gemini' => new GeminiDriver($model, $images),
            'openai' => new OpenAiDriver($model, $images),
            'xai' => new XaiDriver($model, $images),
            default => throw new RuntimeException("Unsupported AI driver: {$name}"),
        };
    }

    /**
     * Register a driver of your own under a name. The closure gets the container and the model
     * asked for with using(), which is null when none was asked for.
     *
     * @param  Closure(Container, ?string): AiContentDriver  $creator
     */
    public function extend(string $name, Closure $creator): self
    {
        $name = $this->normalize($name);

        $this->customCreators[$name] = $creator;
        unset($this->drivers[$name]);

        return $this;
    }

    /**
     * The driver that makes images: the one named, the configured image driver, or else the text
     * driver when it can make images and OpenAI when it cannot. Null when that driver has no images.
     *
     * @throws RuntimeException When no driver has this name
     */
    public function imageDriver(?string $name = null): ?AiImageDriver
    {
        if ($name !== null) {
            $driver = $this->driver($name);

            return $driver instanceof AiImageDriver ? $driver : null;
        }

        return $this->imageDriverFor($this->driver());
    }

    /**
     * The image driver for a text driver: the configured image driver, or else the text driver
     * itself when it can make images, or else OpenAI.
     */
    public function imageDriverFor(AiContentDriver $textDriver): ?AiImageDriver
    {
        $configured = AiGeneratorConfig::imageDriver();

        if ($configured !== null) {
            return $this->imageDriver($configured);
        }

        return $textDriver instanceof AiImageDriver ? $textDriver : $this->imageDriver('openai');
    }

    /**
     * The names of the drivers the package ships and the ones registered with extend().
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique([
            ...array_map(fn (Provider $provider) => $provider->value, Provider::cases()),
            ...array_keys($this->customCreators),
        ]));
    }

    /**
     * Turn an alias such as "claude" into the name of the driver.
     */
    public function normalize(string $name): string
    {
        $name = strtolower(trim($name));

        if (isset($this->customCreators[$name])) {
            return $name;
        }

        return Provider::fromName($name)->value ?? $name;
    }
}
