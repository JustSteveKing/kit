<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

final readonly class LoginPayload
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName,
    ) {}

    public static function fromReqest(Request $request): self
    {
        $data = self::validatedData($request);

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $deviceName = $data['device_name'] ?? '';

        return new self(
            email: is_string($email) ? $email : '',
            password: is_string($password) ? $password : '',
            deviceName: is_string($deviceName) ? $deviceName : '',
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
