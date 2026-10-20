<?php

declare(strict_types=1);

namespace App\Http\Requests\Webhooks;

use App\Webhooks\DeliverableUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every field is optional; only those sent change.
     *
     * @return array<string, mixed>
     */
    public function rules(DeliverableUrl $deliverable): array
    {
        return [
            'url' => ['sometimes', 'required', 'string', 'url', 'max:2048', $deliverable],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'events' => ['sometimes', 'required', 'array', 'min:1'],
            'events.*' => ['required', 'string', 'distinct', Rule::in(['*', ...config()->array('webhooks.events', [])])],
            'enabled' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
