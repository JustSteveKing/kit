<?php

declare(strict_types=1);

namespace App\Http\Payloads;

use Illuminate\Http\Request;
use LogicException;

/**
 * Reads a path parameter for a payload. Path parameters are validated by the route's constraints
 * (whereNumber, whereUlid, where), not by a Form Request: validating them as input would document
 * them as body fields as well as path ones.
 */
final class RouteParameter
{
    public static function string(Request $request, string $name): string
    {
        $value = $request->route($name);

        if ( ! is_string($value)) {
            throw new LogicException("The route has no [{$name}] parameter.");
        }

        return $value;
    }

    public static function integer(Request $request, string $name): int
    {
        return (int) self::string($request, $name);
    }
}
