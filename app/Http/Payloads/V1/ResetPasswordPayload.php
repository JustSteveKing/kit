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

        $token = $data['token'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $passwordConfirmation = $data['password_confirmation'] ?? '';

        return new self(
            token: is_string($token) ? $token : '',
            email: is_string($email) ? $email : '',
            password: is_string($password) ? $password : '',
            passwordConfirmation: is_string($passwordConfirmation) ? $passwordConfirmation : '',
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
