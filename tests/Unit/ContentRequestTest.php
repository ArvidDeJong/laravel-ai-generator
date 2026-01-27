<?php

use Darvis\LaravelAiGenerator\ContentRequest;

test('content request can be created with minimal parameters', function () {
    $request = new ContentRequest(topic: 'Test topic');

    expect($request->topic)->toBe('Test topic')
        ->and($request->language)->toBeNull()
        ->and($request->audience)->toBeNull()
        ->and($request->tone)->toBeNull()
        ->and($request->readingLevel)->toBeNull()
        ->and($request->keywords)->toBeNull()
        ->and($request->cta)->toBeNull()
        ->and($request->brand)->toBeNull()
        ->and($request->maxWords)->toBeNull()
        ->and($request->includeImage)->toBeTrue()
        ->and($request->imageStyle)->toBe('photo')
        ->and($request->imageAspect)->toBe('16:9');
});

test('content request can be created with all parameters', function () {
    $request = new ContentRequest(
        topic: 'Sustainable packaging',
        language: 'nl',
        audience: 'business owners',
        tone: 'formal',
        readingLevel: 'expert',
        keywords: ['eco-friendly', 'packaging'],
        cta: 'Request a quote',
        brand: 'EcoPack BV',
        maxWords: 600,
        includeImage: false,
        imageStyle: 'illustration',
        imageAspect: '1:1',
    );

    expect($request->topic)->toBe('Sustainable packaging')
        ->and($request->language)->toBe('nl')
        ->and($request->audience)->toBe('business owners')
        ->and($request->tone)->toBe('formal')
        ->and($request->readingLevel)->toBe('expert')
        ->and($request->keywords)->toBe(['eco-friendly', 'packaging'])
        ->and($request->cta)->toBe('Request a quote')
        ->and($request->brand)->toBe('EcoPack BV')
        ->and($request->maxWords)->toBe(600)
        ->and($request->includeImage)->toBeFalse()
        ->and($request->imageStyle)->toBe('illustration')
        ->and($request->imageAspect)->toBe('1:1');
});

test('content request is immutable', function () {
    $request = new ContentRequest(topic: 'Test topic');

    expect(fn () => $request->topic = 'New topic')
        ->toThrow(Error::class);
});
