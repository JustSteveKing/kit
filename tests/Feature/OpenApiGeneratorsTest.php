<?php

declare(strict_types=1);

use App\Support\ResponseFieldsOpenApiGenerator;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;

/**
 * @param  list<array<string, mixed>>  $responseFields
 */
function endpointWith(array $responseFields): OutputEndpointData
{
    return new OutputEndpointData([
        'httpMethods' => ['GET'],
        'uri' => 'v1/things',
        'metadata' => ['groupName' => 'Things', 'title' => 'List Things', 'description' => '', 'authenticated' => false],
        'responseFields' => $responseFields,
    ]);
}

/**
 * @param  array<string, mixed>  $properties
 * @return array<string, mixed>
 */
function jsonResponse(array $properties): array
{
    return ['responses' => [200 => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => $properties]]]]]];
}

it('documents an array response field as an OpenAPI array, not Scribe\'s string[]', function (string $scribeType): void {
    $endpoint = endpointWith([['name' => 'data.tags', 'type' => $scribeType, 'description' => 'Tags.', 'required' => false, 'example' => null, 'nullable' => false]]);
    $pathItem = jsonResponse(['data' => ['type' => 'object', 'properties' => ['tags' => ['type' => 'array', 'items' => ['type' => 'string']]]]]);

    $schema = new ResponseFieldsOpenApiGenerator(new DocumentationConfig([]))->pathItem($pathItem, [], $endpoint);

    expect($schema['responses'][200]['content']['application/json']['schema']['properties']['data']['properties']['tags'])
        ->toBe(['type' => 'array', 'items' => ['type' => 'string']]);
})->with(['string[]', 'object[]']);

it('still applies a plain type and nullable', function (): void {
    $endpoint = endpointWith([['name' => 'data.count', 'type' => 'integer', 'description' => 'How many.', 'required' => false, 'example' => null, 'nullable' => true]]);
    $pathItem = jsonResponse(['data' => ['type' => 'object', 'properties' => ['count' => ['type' => 'string']]]]);

    $schema = new ResponseFieldsOpenApiGenerator(new DocumentationConfig([]))->pathItem($pathItem, [], $endpoint);

    expect($schema['responses'][200]['content']['application/json']['schema']['properties']['data']['properties']['count'])
        ->toBe(['type' => 'integer', 'nullable' => true]);
});
