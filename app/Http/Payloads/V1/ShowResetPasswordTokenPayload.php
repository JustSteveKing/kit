<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use App\Http\Payloads\RouteParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final readonly class ShowResetPasswordTokenPayload
{
    public function __construct(
        public string $token,
        public ?string $email,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = self::validatedData($request);

        return new self(
            token: RouteParameter::string($request, 'token'),
            email: isset($data['email']) ? Arr::string($data, 'email') : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function validatedData(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $request instanceof FormRequest ? $request->validated() : $request->all();

        return $data;
    }
}
