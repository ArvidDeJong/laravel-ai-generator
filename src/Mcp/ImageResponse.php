<?php

namespace Darvis\LaravelAiGenerator\Mcp;

use Laravel\Mcp\Response;

/**
 * Turns base64 image data into an MCP image response with the right MIME type. The providers
 * return PNG, JPEG or WebP depending on the model, and do not all say which.
 *
 * @internal
 */
final class ImageResponse
{
    public static function fromBase64(string $base64): Response
    {
        // The image content encodes the data itself, so it gets the raw bytes.
        $binary = (string) base64_decode($base64);
        $head = substr($binary, 0, 12);

        $mimeType = match (true) {
            str_starts_with($head, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'image/webp',
            str_starts_with($head, 'GIF8') => 'image/gif',
            default => 'image/png',
        };

        return Response::image($binary, $mimeType);
    }
}
