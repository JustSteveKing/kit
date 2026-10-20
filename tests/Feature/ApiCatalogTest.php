<?php

declare(strict_types=1);

it('advertises the v1 api and its openapi description in the api catalog', function (): void {
    $response = $this->get('/.well-known/api-catalog')
        ->assertOk()
        ->assertApiCatalog(url('/v1'));

    expect($response->json('linkset.1.anchor'))->toBe(url('/v1'));
    expect($response->json('linkset.1.service-desc.0.href'))->toBe(url('/docs/openapi.yaml'));
});
