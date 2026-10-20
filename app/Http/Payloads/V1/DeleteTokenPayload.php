<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use App\Http\Payloads\RouteParameter;
use Illuminate\Http\Request;

final readonly class DeleteTokenPayload
{
    public function __construct(
        public int $tokenId,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            tokenId: RouteParameter::integer($request, 'token_id'),
        );
    }
}
