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

        return new self(
            token: (string) $data['token'],
            email: isset($data['email']) ? (string) $data['email'] : null,
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
