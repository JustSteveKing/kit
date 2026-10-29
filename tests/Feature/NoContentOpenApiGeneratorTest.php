<?php

declare(strict_types=1);

use App\Support\NoContentOpenApiGenerator;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;

it('documents a status that carries no body without one', function (int $status): void {
    $endpoint = new OutputEndpointData([
        'httpMethods' => ['GET'],
        'uri' => 'v1/things/{thing}',
        'metadata' => ['groupName' => 'Things', 'title' => 'Show Thing', 'description' => '', 'authenticated' => false],
    ]);
    $pathItem = ['responses' => [
        $status => ['description' => 'No body.', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
        200 => ['description' => 'The thing.', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ]];

    $documented = new NoContentOpenApiGenerator(new DocumentationConfig([]))->pathItem($pathItem, [], $endpoint);

    expect($documented['responses'][$status])->toBe(['description' => 'No body.'])
        ->and($documented['responses'][200])->toHaveKey('content');
})->with(['204 No Content' => 204, '303 See Other' => 303, '304 Not Modified' => 304]);
