<?php

use Darvis\LaravelAiGenerator\Support\EnvFile;

function tempEnv(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($path, $contents);

    return $path;
}

test('a variable is replaced where it stands and a new one is appended', function () {
    $path = tempEnv("APP_NAME=Demo\n# OpenAI\nOPENAI_API_KEY=old\nAPP_DEBUG=true\n");

    (new EnvFile($path))->set(['OPENAI_API_KEY' => 'sk-new', 'ANTHROPIC_API_KEY' => 'sk-ant']);

    expect(file_get_contents($path))->toBe("APP_NAME=Demo\n# OpenAI\nOPENAI_API_KEY=sk-new\nAPP_DEBUG=true\n\nANTHROPIC_API_KEY=sk-ant\n");
});

test('values that the env parser would misread are quoted, and read back unquoted', function () {
    $path = tempEnv('');
    $env = new EnvFile($path);

    $env->set(['A' => 'with space', 'B' => 'has#hash', 'C' => null, 'D' => 'a$b']);

    expect(file_get_contents($path))->toBe("A=\"with space\"\nB=\"has#hash\"\nC=\nD=\"a\\\$b\"\n")
        ->and($env->get('A'))->toBe('with space')
        ->and($env->get('D'))->toBe('a$b')
        ->and($env->get('C'))->toBe('')
        ->and($env->get('MISSING'))->toBeNull();
});

test('a value with a backreference character is written literally', function () {
    $path = tempEnv("KEY=old\n");

    (new EnvFile($path))->set(['KEY' => 'x\\1y$0']);

    expect((new EnvFile($path))->get('KEY'))->toBe('x\\1y$0');
});
