<?php

declare(strict_types=1);

namespace App\Support;

use Knuckles\Camel\Extraction\ResponseField;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

/**
 * Applies each #[ResponseField]'s type and nullable to the documented response schemas. Scribe
 * infers a property's type from its example, so a null example documents an integer as a string,
 * and it ignores fields inside arrays. Names are dotted paths; "[]" steps into an array's items, as
 * in data.allowed_rate, [].allowed_rate or data.attempts[].response_status.
 */
final class ResponseFieldsOpenApiGenerator extends OpenApiGenerator
{
    /**
     * @param  array<string, mixed>  $pathItem
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     * @return array<string, mixed>
     */
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        if ([] === $endpoint->responseFields || ! is_array($pathItem['responses'] ?? null)) {
            return $pathItem;
        }

        foreach ($pathItem['responses'] as $status => $response) {
            $content = is_array($response) ? ($response['content'] ?? null) : null;
            $json = is_array($content) ? ($content['application/json'] ?? null) : null;
            $schema = is_array($json) ? ($json['schema'] ?? null) : null;

            if ((int) $status >= 400 || ! is_array($response) || ! is_array($content) || ! is_array($json) || ! is_array($schema)) {
                continue;
            }

            /** @var array<string, mixed> $typed */
            $typed = $schema;

            foreach ($endpoint->responseFields as $field) {
                $typed = $this->apply($typed, $this->path($field->name), $field);
            }

            $json['schema'] = $typed;
            $content['application/json'] = $json;
            $response['content'] = $content;
            $pathItem['responses'][$status] = $response;
        }

        return $pathItem;
    }

    /**
     * @return list<string> Segments, with "[]" as its own segment for an array's items.
     */
    private function path(string $name): array
    {
        $segments = [];

        foreach (explode('.', $name) as $part) {
            $array = str_ends_with($part, '[]');
            $part = $array ? mb_substr($part, 0, -2) : $part;

            if ('' !== $part) {
                $segments[] = $part;
            }

            if ($array) {
                $segments[] = '[]';
            }
        }

        return $segments;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $path
     * @return array<string, mixed>
     */
    private function apply(array $schema, array $path, ResponseField $field): array
    {
        if ([] === $path) {
            if ('' !== $field->type) {
                $schema['type'] = $field->type;
            }

            if (true === $field->nullable) {
                $schema['nullable'] = true;
            }

            return $schema;
        }

        $segment = array_shift($path);

        if ('[]' === $segment) {
            $items = $schema['items'] ?? null;

            if (is_array($items)) {
                /** @var array<string, mixed> $items */
                $schema['items'] = $this->apply($items, $path, $field);
            }

            return $schema;
        }

        $properties = $schema['properties'] ?? null;
        $property = is_array($properties) ? ($properties[$segment] ?? null) : null;

        if (is_array($properties) && is_array($property)) {
            /** @var array<string, mixed> $property */
            $properties[$segment] = $this->apply($property, $path, $field);
            $schema['properties'] = $properties;
        }

        return $schema;
    }
}
