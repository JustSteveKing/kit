<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ShowResetPasswordTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Read by Scribe: the request is a GET, so its rules describe query parameters, and the token is
     * a path parameter validated by the route.
     *
     * @return array<string, array{description: string, example: string}>
     */
    public function queryParameters(): array
    {
        return [
            'email' => [
                'description' => 'Account email from the reset link.',
                'example' => 'jane@example.com',
            ],
        ];
    }
}
