<?php

declare(strict_types=1);

namespace App\Support;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

/**
 * Describes the standard request headers an endpoint documents with #[Header]. Scribe's attribute
 * takes a name and an example but no description, so every documented header would otherwise reach
 * the spec with an empty one, which legible reports and an agent cannot act on. A description an
 * operation already has is left alone.
 */
final class HeaderDescriptionsOpenApiGenerator extends OpenApiGenerator
{
    /**
     * Keyed by lowercase header name.
     */
    public const array DESCRIPTIONS = [
        'idempotency-key' => 'A key unique to this request, such as a UUID. Send the same key when retrying: a retry with the same key and body gets the original response instead of running again, for a day. The same key with a different body is a 422.',
        'if-match' => 'The ETag from your last GET of this resource. The change is refused with 412 if the resource has changed since, so it cannot overwrite someone else\'s change. Leaving it out is a 428 where the endpoint requires it.',
        'if-none-match' => 'An ETag from an earlier response. While it still matches, the answer is 304 Not Modified with no body, so you can re-fetch cheaply.',
        'prefer' => 'Preferences for how the request is handled, as RFC 7240 defines them, such as return=minimal.',
        'accept-language' => 'The language for messages people read, such as an error\'s detail. English and Spanish are supported; type and title never change with it.',
    ];

    /**
     * @param  array<string, mixed>  $pathItem
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     * @return array<string, mixed>
     */
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        if ( ! is_array($pathItem['parameters'] ?? null)) {
            return $pathItem;
        }

        foreach ($pathItem['parameters'] as $index => $parameter) {
            if ( ! is_array($parameter) || 'header' !== ($parameter['in'] ?? null) || ! is_string($parameter['name'] ?? null)) {
                continue;
            }

            $description = self::DESCRIPTIONS[mb_strtolower($parameter['name'])] ?? null;

            if (null !== $description && '' === ($parameter['description'] ?? '')) {
                $parameter['description'] = $description;
                $pathItem['parameters'][$index] = $parameter;
            }
        }

        return $pathItem;
    }
}
