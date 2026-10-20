<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use App\Http\Payloads\RouteParameter;
use Illuminate\Http\Request;

final readonly class VerifyEmailPayload
{
    public function __construct(
        public string $id,
        public string $hash,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            id: RouteParameter::string($request, 'id'),
            hash: RouteParameter::string($request, 'hash'),
        );
    }
}
