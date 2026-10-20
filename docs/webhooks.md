# Webhooks

Kit sends outbound webhooks. Your API's consumers subscribe their own endpoints through the API,
and Kit delivers events to them as signed [CloudEvents](https://github.com/cloudevents/spec),
retrying failures and recording every attempt.

Everything up to [Adding an event](#adding-an-event) is the contract your consumers rely on, written
so you can lift it into your own API's documentation.

Kit ships the machinery and one event, `webhook.ping`. Everything else is yours to add.

## Managing subscriptions

The endpoints need the `webhooks:read` or `webhooks:write` ability, which session tokens have by
default.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/v1/webhooks` | List subscriptions |
| POST | `/v1/webhooks` | Subscribe a URL to events |
| GET | `/v1/webhooks/{webhook}` | Show a subscription, and why it was disabled if it was |
| PATCH | `/v1/webhooks/{webhook}` | Change the URL, events or description, or enable and disable it |
| DELETE | `/v1/webhooks/{webhook}` | Delete a subscription and its history |
| POST | `/v1/webhooks/{webhook}/secret` | Rotate the signing secret |
| POST | `/v1/webhooks/{webhook}/ping` | Send a `webhook.ping` |
| POST | `/v1/webhooks/{webhook}/validate` | Ask the endpoint for consent again |
| GET, POST | `/v1/webhooks/{webhook}/consent` | The signed consent link sent to the endpoint |
| GET | `/v1/webhooks/{webhook}/deliveries` | Deliveries, each with every attempt |
| POST | `/v1/webhooks/{webhook}/deliveries/{delivery}/redeliver` | Send an event again |

Subscribe:

```bash
curl -X POST http://127.0.0.1:8000/v1/webhooks \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"url": "https://example.com/hooks", "events": ["*"], "description": "Production"}'
```

`events` lists the types to receive, or `*` for everything. The signing secret comes back in
`meta.secret`, and this is the only response that ever contains it. Rotating the secret returns the
new one the same way.

Send a ping next, then look at `GET /v1/webhooks/{webhook}/deliveries` to see what the endpoint
said.

## What a delivery looks like

Each delivery is a CloudEvents 1.0 event in structured mode, POSTed as
`application/cloudevents+json`:

```json
{
  "specversion": "1.0",
  "id": "01jb8x3a1b2c3d4e5f6g7h8j9k",
  "source": "https://api.example.com/v1",
  "type": "project.created",
  "subject": "01jb8x2m4k5n6p7q8r9s0t1v2w",
  "time": "2026-10-20T12:01:00+00:00",
  "datacontenttype": "application/json",
  "data": {}
}
```

- `id` is unique to the event, and the same on every retry and redelivery.
- `source` identifies your API. It is `WEBHOOKS_SOURCE`, which defaults to `APP_URL` plus `/v1`.
- `type` is what happened, dotted and past tense.
- `subject` is what it happened to, such as the project's ID, and is left out when there is none.
- `data` is the event's own payload.

Any CloudEvents SDK can parse it.

Delivery itself follows the [CloudEvents webhook specification](https://github.com/cloudevents/spec/blob/main/cloudevents/http-webhook.md),
and every request carries `WebHook-Request-Origin`, set to `WEBHOOKS_ORIGIN` (`APP_URL`'s host
unless you change it).

## Verifying a delivery

Every delivery is signed with [RFC 9421](https://www.rfc-editor.org/rfc/rfc9421) HTTP Message
Signatures, in the `Signature` and `Signature-Input` headers, using HMAC-SHA256 and the
subscription's secret. The `keyid` is the subscription ID. The signature covers the method, target
URI, authority and path, the `Content-Type`, and a `Content-Digest` of the body, so changing any of
them in transit breaks it.

On a Laravel receiver with api-toolbox, the `signature.verify` middleware checks it. Configure the
secret as a key named after the subscription ID:

```php
// config/api-toolbox.php, on the receiving side
Features::messageSignatures([
    'keys' => [
        '01jb8x2m4k5n6p7q8r9s0t1v2w' => ['algorithm' => 'hmac-sha256', 'secret' => env('KIT_WEBHOOK_SECRET')],
    ],
]),
```

Other stacks can use any RFC 9421 library.

## Handling duplicates

The event ID goes out in an `Idempotency-Key` header, as a quoted string. A receiver that records
the keys it has handled can skip anything it has already processed, and it should, because retries
happen. An endpoint that does the work and then times out will see the same event again.

## Retries and failures

Any `2xx` response is success. Anything else is retried after 10 seconds, 1 minute, 5 minutes, 30
minutes, 2 hours and 6 hours, and then the delivery is marked failed.

Some responses change that:

| Response | What happens |
| --- | --- |
| `429` | Retried no sooner than its `Retry-After` |
| `410 Gone` | Deliveries stop and the subscription is disabled |
| `415` | Fails without retrying, since the format will not change |

After 10 deliveries in a row fail, the subscription is disabled, with the reason in
`disabled_reason`. Its owner re-enables it with `PATCH {"enabled": true}`.

A delivery that failed, or one the receiver lost, can be sent again with the redeliver endpoint.
The receiver gets the same event with the same ID.

## Delivery history

`GET /v1/webhooks/{webhook}/deliveries` lists deliveries newest first. Each has its state
(`pending`, `retrying`, `succeeded` or `failed`), when the next attempt is due, and every attempt so
far, with the status, an excerpt of the response, how long it took and any error. A `null` status
means no response arrived at all, which is a different problem from an error status.

Events and their deliveries are kept for 30 days (`WEBHOOKS_RETENTION_DAYS`).

## Endpoint consent

Anyone with a token can subscribe any public URL, so a webhook system can be used to point traffic
at someone who never asked for it. With `WEBHOOKS_VALIDATE_ENDPOINTS=true`, a subscription receives
nothing until its endpoint consents through the CloudEvents handshake:

1. Kit sends `OPTIONS` to the URL, with `WebHook-Request-Origin`.
2. The endpoint answers with `WebHook-Allowed-Origin` set to that origin, or `*`.

Only that header counts as consent; the status code is ignored. When it arrives, the
subscription's `validated_at` is set.

An endpoint that cannot answer straight away can consent later. The `OPTIONS` request carries a
`WebHook-Request-Callback` URL, a signed link that stays valid for `WEBHOOKS_CALLBACK_HOURS` (three
days by default). A GET or a POST to it grants consent, unless the subscription's URL has changed
since, and a newer validation request replaces the old link.

Changing a subscription's URL asks for consent again, and `POST /v1/webhooks/{webhook}/validate`
asks again on demand.

Validation is off by default, since it means every receiver has to implement the handshake. Turn it
on once your consumers are outside your own organisation.

## Delivery rate

An endpoint can slow deliveries down by including `WebHook-Allowed-Rate` in its handshake response
or consent callback, as deliveries a minute, or `*` for no limit. Kit stays inside it. A delivery
over the rate waits its turn, and the wait does not use up a retry. The subscription shows the
granted rate as `allowed_rate`.

Set `WEBHOOKS_REQUEST_RATE` to ask each endpoint for a rate with `WebHook-Request-Rate`. That rate
applies when an endpoint consents without granting one.

Rate limiting needs a queue that can delay jobs, which rules out the `sync` driver.

## Where subscriptions can point

Subscription URLs are user input, and a webhook sender that will call anything is a way into your
own network. Kit refuses URLs that are not https, and URLs whose host resolves to a private,
loopback or reserved address. The check runs when the subscription is made and again on every
send, and the connection is pinned to the address that was checked, so DNS cannot change in
between. Redirects are never followed.

To test against a local receiver, loosen both locally:

```dotenv
WEBHOOKS_REQUIRE_HTTPS=false
WEBHOOKS_ALLOW_PRIVATE_NETWORKS=true
```

## Adding an event

An event is a class implementing `App\Webhooks\WebhookEvent`:

```php
final readonly class ProjectCreated implements WebhookEvent
{
    public function __construct(
        private Project $project,
    ) {}

    public function type(): string
    {
        return 'project.created';
    }

    public function subject(): string
    {
        return (string) $this->project->id;
    }

    /**
     * @return array{id: string, name: string}
     */
    public function data(): array
    {
        return [
            'id' => (string) $this->project->id,
            'name' => $this->project->name,
        ];
    }
}
```

Add its type to `events` in `config/webhooks.php`, so subscriptions can ask for it:

```php
'events' => [
    'project.created',
],
```

Then dispatch it where the change happens, for the user who owns the data:

```php
DB::transaction(function () use ($user, $payload): void {
    $project = $user->projects()->create(['name' => $payload->name]);

    app(Webhooks::class)->dispatch($user, new ProjectCreated($project));
});
```

Dispatch inside the transaction. Deliveries are only queued once it commits, so a subscriber is
never told about a row it then cannot fetch.

Treat `data()` as public API, because subscribers parse it. Add fields freely, but do not rename or
remove one.

Do not send webhooks with `Http` directly. Signing, the address checks, the record of each attempt
and the retries all live in the `Webhooks` service and the `DeliverWebhook` job, and a hand-rolled
request skips the lot.

## Running it

Deliveries go through the queue, and a daily `model:prune` clears out old events, so production
needs a queue worker and the scheduler alongside the web server. [Deployment](deployment.md) covers
both.

## Testing

Bind `App\Webhooks\HostResolver` to answer without DNS, and fake the HTTP client:

```php
app()->instance(HostResolver::class, new class implements HostResolver {
    public function addresses(string $host): array
    {
        return ['93.184.216.34'];
    }
});

Http::fake(['https://example.com/*' => Http::response(status: 204)]);
```

`tests/Feature/WebhookDeliveryTest.php` has examples of retries, signatures and disabled
endpoints.
