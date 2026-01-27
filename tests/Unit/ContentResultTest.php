<?php

use Darvis\LaravelAiGenerator\ContentResult;

test('content result can be created with required parameters', function () {
    $result = new ContentResult(
        title: 'Test Title',
        intro: 'Test introduction text.',
        text: '<p>Test content</p>',
        seoTitle: 'SEO Title',
        seoDescription: 'SEO description for the content.',
    );

    expect($result->title)->toBe('Test Title')
        ->and($result->intro)->toBe('Test introduction text.')
        ->and($result->text)->toBe('<p>Test content</p>')
        ->and($result->seoTitle)->toBe('SEO Title')
        ->and($result->seoDescription)->toBe('SEO description for the content.')
        ->and($result->imagePrompt)->toBeNull()
        ->and($result->imageUrl)->toBeNull()
        ->and($result->imageBase64)->toBeNull()
        ->and($result->errorMessage)->toBeNull();
});

test('content result can include image data', function () {
    $result = new ContentResult(
        title: 'Test Title',
        intro: 'Test introduction.',
        text: '<p>Content</p>',
        seoTitle: 'SEO Title',
        seoDescription: 'Description',
        imagePrompt: 'A beautiful landscape',
        imageUrl: 'https://example.com/image.jpg',
    );

    expect($result->imagePrompt)->toBe('A beautiful landscape')
        ->and($result->imageUrl)->toBe('https://example.com/image.jpg')
        ->and($result->hasImage())->toBeTrue();
});

test('content result can include base64 image', function () {
    $result = new ContentResult(
        title: 'Test Title',
        intro: 'Test introduction.',
        text: '<p>Content</p>',
        seoTitle: 'SEO Title',
        seoDescription: 'Description',
        imageBase64: 'base64encodedstring',
    );

    expect($result->imageBase64)->toBe('base64encodedstring')
        ->and($result->hasImage())->toBeTrue();
});

test('hasImage returns false when no image data', function () {
    $result = new ContentResult(
        title: 'Test Title',
        intro: 'Test introduction.',
        text: '<p>Content</p>',
        seoTitle: 'SEO Title',
        seoDescription: 'Description',
    );

    expect($result->hasImage())->toBeFalse();
});

test('hasError returns true when error message is present', function () {
    $result = new ContentResult(
        title: 'Test Title',
        intro: 'Test introduction.',
        text: '<p>Content</p>',
        seoTitle: 'SEO Title',
        seoDescription: 'Description',
        errorMessage: 'Something went wrong',
    );

    expect($result->hasError())->toBeTrue()
        ->and($result->errorMessage)->toBe('Something went wrong');
});

test('hasError returns false when no error', function () {
    $result = new ContentResult(
        title: 'Test Title',
        intro: 'Test introduction.',
        text: '<p>Content</p>',
        seoTitle: 'SEO Title',
        seoDescription: 'Description',
    );

    expect($result->hasError())->toBeFalse();
});

test('content result is immutable', function () {
    $result = new ContentResult(
        title: 'Test Title',
        intro: 'Test introduction.',
        text: '<p>Content</p>',
        seoTitle: 'SEO Title',
        seoDescription: 'Description',
    );

    expect(fn () => $result->title = 'New Title')
        ->toThrow(Error::class);
});
