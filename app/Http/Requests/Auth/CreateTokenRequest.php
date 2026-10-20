<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\AccessTokens;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

final class CreateTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An API key can only hold abilities the token creating it holds, so a narrow key can never
     * mint a broader one.
     *
     * @return array<string, mixed>
     */
    public function rules(AccessTokens $tokens): array
    {
        $current = $this->user()?->currentAccessToken();

        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [
                'required',
                'string',
                'distinct',
                Rule::in($current instanceof PersonalAccessToken ? $tokens->grantableBy($current) : []),
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after:now',
                'before_or_equal:' . $tokens->latestApiKeyExpiry()->toAtomString(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'abilities.*.in' => __('api.auth.ability_not_grantable'),
        ];
    }
}
