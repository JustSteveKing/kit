<?php

declare(strict_types=1);

namespace App\Problems;

use JustSteveKing\Toolbox\ProblemDetails\Attributes\ProblemType;
use JustSteveKing\Toolbox\ProblemDetails\ProblemException;
use JustSteveKing\Toolbox\Support\ApiGuide;

/**
 * An endpoint called after its #[Sunset] date (apiguide.dev's endpoint-sunset). Thrown by
 * RetireSunsetEndpoints, with deprecatedAt, sunsetAt and migrationGuide in the body.
 */
#[ProblemType(ApiGuide::ENDPOINT_SUNSET, title: 'Endpoint Sunset', status: 410)]
final class EndpointSunset extends ProblemException {}
