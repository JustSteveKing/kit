<?php

declare(strict_types=1);

namespace App\Http\Requests\Webhooks;

use App\Webhooks\DeliverableUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(DeliverableUrl $deliverable): array
    {
        return [
            'url' => ['required', 'string', 'url', 'max:2048', $deliverable],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', 'distinct', Rule::in(['*', ...config()->array('webhooks.events', [])])],
        ];
    }
}
