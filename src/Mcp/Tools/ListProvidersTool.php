<?php

namespace Darvis\LaravelAiGenerator\Mcp\Tools;

use Darvis\LaravelAiGenerator\AiGeneratorManager;
use Darvis\LaravelAiGenerator\Drivers\Driver;
use Darvis\LaravelAiGenerator\Support\AiGeneratorConfig;
use Darvis\LaravelAiGenerator\Support\Provider;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;

#[Description('Lists the AI providers of this application: which ones have an API key, their text and image models, the default driver, the image driver and the fallbacks. Never returns a key.')]
#[IsReadOnly]
class ListProvidersTool extends Tool
{
    public function handle(Request $request, AiGeneratorManager $manager): Response
    {
        $providers = [];

        foreach (Provider::cases() as $provider) {
            $providers[] = [
                'driver' => $provider->value,
                'name' => $provider->label(),
                'has_api_key' => AiGeneratorConfig::apiKey($provider) !== null,
                'text_model' => AiGeneratorConfig::model($provider),
                'image_model' => AiGeneratorConfig::imageModel($provider),
            ];
        }

        try {
            $image = $manager->imageDriverFor($manager->driver());
        } catch (RuntimeException) {
            $image = null;
        }

        return Response::json([
            'default_driver' => $manager->normalize(AiGeneratorConfig::driver()),
            'image_driver' => $image instanceof Driver ? $image->provider()->value : null,
            'fallbacks' => AiGeneratorConfig::fallbacks(),
            'default_language' => AiGeneratorConfig::defaultLanguage(),
            'providers' => $providers,
        ]);
    }
}
