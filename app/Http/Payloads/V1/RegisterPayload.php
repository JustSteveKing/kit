<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

final readonly class RegisterPayload
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $deviceName,
    ) {}

    public static function fromReqest(Request $request): self
    {
        $data = self::validatedData($request);

        return new self(
            name: (string) $data['name'],
            email: (string) $data['email'],
            password: (string) $data['password'],
            deviceName: isset($data['device_name']) && $data['device_name'] !== ''
                ? (string) $data['device_name']
                : 'api-client',
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
