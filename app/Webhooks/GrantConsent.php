<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Http\Payloads\RouteParameter;
use App\Models\WebhookSubscription;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Records consent given through a WebHook-Request-Callback URL, which the CloudEvents webhook
 * specification says must accept both GET and POST (section 4.1.3). Each method has its own
 * controller so each is documented as its own operation.
 */
final class GrantConsent
{
    public function __invoke(Request $request): JsonResponse
    {
        // Checked here rather than by the signed middleware, whose 403 talks about email links.
        if ( ! $request->hasValidSignature()) {
            abort(403, __('api.webhooks.invalid_consent_link'));
        }

        $subscription = WebhookSubscription::query()->findOrFail(RouteParameter::string($request, 'webhook'));

        if ( ! hash_equals(ConsentCallback::urlHash($subscription->url), $request->string('url_hash')->value())) {
            abort(409, __('api.webhooks.consent_url_changed'));
        }

        // A later validation request replaced the one this link was sent with.
        if (null === $subscription->validation_request || ! hash_equals($subscription->validation_request, $request->string('request')->value())) {
            abort(409, __('api.webhooks.consent_superseded'));
        }

        // Applied on every call, so a callback answering a re-validation updates the granted rate.
        $subscription->forceFill([
            'validated_at' => now(),
            'allowed_rate' => AllowedRate::from($request->header('WebHook-Allowed-Rate')),
        ])->save();

        SecurityAudit::log('webhooks.consented', [
            'user_id' => $subscription->user_id,
            'webhook_id' => $subscription->id,
            'via' => 'callback',
            'allowed_rate' => $subscription->allowed_rate,
        ]);

        return new JsonResponse(['message' => __('api.webhooks.consent_granted')]);
    }
}
