<?php

use Darvis\LaravelAiGenerator\AiGenerator;
use Darvis\LaravelAiGenerator\ContentRequest;
use Darvis\LaravelAiGenerator\ContentResult;
use Darvis\LaravelAiGenerator\Contracts\AiContentDriver;

test('ai generator can be resolved from container', function () {
    $generator = app(AiGenerator::class);

    expect($generator)->toBeInstanceOf(AiGenerator::class);
});

test('ai generator can be resolved via alias', function () {
    $generator = app('ai-generator');

    expect($generator)->toBeInstanceOf(AiGenerator::class);
});

test('ai generator merges defaults from config', function () {
    config([
        'ai-generator.default_language' => 'en',
        'ai-generator.defaults.tone' => 'formal',
        'ai-generator.defaults.reading_level' => 'expert',
        'ai-generator.defaults.max_words' => 500,
    ]);

    $mockDriver = Mockery::mock(AiContentDriver::class);
    $mockDriver->shouldReceive('generate')
        ->once()
        ->withArgs(function (ContentRequest $request) {
            return $request->language === 'en'
                && $request->tone === 'formal'
                && $request->readingLevel === 'expert'
                && $request->maxWords === 500;
        })
        ->andReturn(new ContentResult(
            title: 'Test',
            intro: 'Intro',
            text: 'Text',
            seoTitle: 'SEO',
            seoDescription: 'Desc',
        ));

    $generator = new AiGenerator($mockDriver);
    $generator->generate(new ContentRequest(topic: 'Test topic'));
});

test('ai generator uses request values over defaults', function () {
    config([
        'ai-generator.default_language' => 'en',
        'ai-generator.defaults.tone' => 'formal',
    ]);

    $mockDriver = Mockery::mock(AiContentDriver::class);
    $mockDriver->shouldReceive('generate')
        ->once()
        ->withArgs(function (ContentRequest $request) {
            return $request->language === 'nl'
                && $request->tone === 'informal';
        })
        ->andReturn(new ContentResult(
            title: 'Test',
            intro: 'Intro',
            text: 'Text',
            seoTitle: 'SEO',
            seoDescription: 'Desc',
        ));

    $generator = new AiGenerator($mockDriver);
    $generator->generate(new ContentRequest(
        topic: 'Test topic',
        language: 'nl',
        tone: 'informal',
    ));
});

test('ai generator sanitizes result output', function () {
    $mockDriver = Mockery::mock(AiContentDriver::class);
    $mockDriver->shouldReceive('generate')
        ->once()
        ->andReturn(new ContentResult(
            title: '  Test Title  ',
            intro: '  Intro with spaces  ',
            text: '  <p>Text</p>  ',
            seoTitle: '  SEO Title  ',
            seoDescription: '  Description  ',
            imagePrompt: '  A prompt  ',
        ));

    $generator = new AiGenerator($mockDriver);
    $result = $generator->generate(new ContentRequest(topic: 'Test'));

    expect($result->title)->toBe('Test Title')
        ->and($result->intro)->toBe('Intro with spaces')
        ->and($result->text)->toBe('<p>Text</p>')
        ->and($result->seoTitle)->toBe('SEO Title')
        ->and($result->seoDescription)->toBe('Description')
        ->and($result->imagePrompt)->toBe('A prompt');
});
