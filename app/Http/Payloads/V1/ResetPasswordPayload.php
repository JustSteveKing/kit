<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

final readonly class ResetPasswordPayload
{
    public function __construct(
        public string $token,
        public string $email,
        public string $password,
        public string $passwordConfirmation,
    ) {}

    public static function fromReqest(Request $request): self
    {
        $data = self::validatedData($request);

        return new self(
            token: (string) $data['token'],
            email: (string) $data['email'],
            password: (string) $data['password'],
            passwordConfirmation: (string) $data['password_confirmation'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function validatedData(Request $request): array
    {
        if ($request instanceof FormRequest) {
            return $request->validated();
        }

        return $request->all();
    }
}
