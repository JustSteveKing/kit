<?php

declare(strict_types=1);

namespace App\Support;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

/**
 * Documents 204 and 304 responses without content. Scribe gives every response a JSON body, even
 * one declared as content: null, but RFC 9110 sections 15.3.5 and 15.4.5 say these responses have
 * none.
 */
final class NoContentOpenApiGenerator extends OpenApiGenerator
{
    private const array STATUSES = [204, 304];

    /**
     * @param  array<string, mixed>  $pathItem
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     * @return array<string, mixed>
     */
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        if ( ! is_array($pathItem['responses'] ?? null)) {
            return $pathItem;
        }

        foreach ($pathItem['responses'] as $status => $response) {
            if (in_array((int) $status, self::STATUSES, true) && is_array($response)) {
                unset($response['content']);
                $pathItem['responses'][$status] = $response;
            }
        }

        return $pathItem;
    }
}
