<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

final readonly class ShowResetPasswordTokenPayload
{
    public function __construct(
        public string $token,
        public ?string $email,
    ) {}

    public static function fromReqest(Request $request): self
    {
        $data = self::validatedData($request);

        $token = $data['token'] ?? '';
        $email = $data['email'] ?? null;

        return new self(
            token: is_string($token) ? $token : '',
            email: is_string($email) ? $email : null,
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
