# Localization

Kit answers in the language the request asks for in `Accept-Language`, and says which one it used
in `Content-Language`. English and Spanish ship with it.

```bash
curl -i http://127.0.0.1:8000/v1/auth/me -H "Accept-Language: es"
```

```http
HTTP/1.1 401 Unauthorized
Content-Type: application/problem+json
Content-Language: es
```

## How the language is chosen

`SetRequestLocale` runs first in the `api` middleware group. It compares `Accept-Language` with
`APP_SUPPORTED_LOCALES` (`en,es`), takes the client's quality values into account, and uses the best
match. A regional tag matches its language, so `es-MX` gets `es`.

A request with no `Accept-Language`, or with only languages Kit
does not support, gets the **first** locale in `APP_SUPPORTED_LOCALES`, not `APP_FALLBACK_LOCALE`.
The order of that list is your default, so keep your main language at the front. The fallback
locale only comes into play when a translation key is missing.

`Content-Language` is exposed through CORS, so browser clients can read it.

## What gets translated

The rule is that anything a person reads gets translated, and anything a client branches on does
not. A problem's `detail` is translated, while its `type` and `title` stay the same in every
language, which is why clients should key on `type`.

The text comes from a few places:

| Messages | Where they live |
| --- | --- |
| Validation | `lang/{locale}/validation.php`, as in any Laravel app |
| Kit's own, such as "The provided credentials are incorrect." | `lang/{locale}/api.php` |
| Problems api-toolbox raises, such as an idempotency conflict | English ships with the package; Spanish is in `lang/vendor/api-toolbox/es/problems.php` |

Emails go out in the language of the request that triggered them, even though they are sent from
the queue.

Two tests keep the translations complete. One fails when `lang/es/api.php` is missing a key that
`lang/en/api.php` has. The other fails when an api-toolbox upgrade adds a problem without a Spanish
line, which is how you find out about new messages before a client does.

## Writing translatable code

Every message a client can read goes through `__()`, with a key in each language:

```php
abort(404, __('api.projects.not_found'));
```

```php
// lang/en/api.php
'projects' => [
    'not_found' => 'The project was not found.',
],

// lang/es/api.php
'projects' => [
    'not_found' => 'No se encontró el proyecto.',
],
```

Field names and data stay as they are. Only the messages around them are translated.

In tests, assert the message through `__()` as well, so the test passes whichever language it runs
in:

```php
->assertJsonPath('detail', __('api.projects.not_found'));
```

## Adding a language

To add French:

1. Add it to `APP_SUPPORTED_LOCALES`: `en,es,fr`.
2. Create `lang/fr/api.php` with every key from `lang/en/api.php`.
3. Create `lang/fr/validation.php`. The `laravel-lang/lang` package is a good source, or copy
   `lang/en/validation.php` and translate it.
4. Create `lang/vendor/api-toolbox/fr/problems.php`, using the Spanish file as the list of keys.

The completeness tests check Spanish by name. Extend them to cover French too, or a missing line
will show up as English in front of a French client instead of failing CI.

## Removing a language

Take it out of `APP_SUPPORTED_LOCALES`, then delete its files and the tests that check them. Kit is
happy with a single locale.
