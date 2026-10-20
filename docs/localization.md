# Localization

Kit picks each response's language from the request's `Accept-Language` header and says which one
it used in `Content-Language`. It ships English and Spanish.

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
`APP_SUPPORTED_LOCALES` (`en,es`), honouring the client's quality values, and uses the best match.
A regional tag matches its language, so `es-MX` gets `es`.

A request with no `Accept-Language`, or none that Kit supports, gets the **first** locale in
`APP_SUPPORTED_LOCALES`. That list's order is your default, so keep your main language first.
`APP_FALLBACK_LOCALE` still matters, but only for a translation key a locale is missing.

`Content-Language` is exposed through CORS, so browser clients can read it.

## What gets translated

Anything a client reads as prose, and nothing a client branches on.

- **Problem `detail`** is translated. **`type` and `title`** never change with the language, which
  is why clients should key on `type`.
- **Validation messages** come from `lang/{locale}/validation.php`, as in any Laravel app.
- **Kit's own messages**, such as "The provided credentials are incorrect.", are in
  `lang/{locale}/api.php`.
- **Problems api-toolbox raises**, such as an idempotency conflict or an unsupported media type,
  ship in English with the package. The Spanish is in `lang/vendor/api-toolbox/es/problems.php`.
- **Emails** are sent in the language of the request that caused them, even though they are
  queued.

Two tests keep the translations whole: one fails when `lang/es/api.php` is missing a key that
`lang/en/api.php` has, and another fails when an api-toolbox upgrade adds a problem with no Spanish
line.

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

Field names and data stay as they are. Translate the messages around them.

In tests, assert the message through `__()` too, so the test does not care which language it ran
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

The completeness tests check Spanish by name. Extend them to the new locale so a missing line
fails CI rather than showing English to a French client.

## Removing a language

Take it out of `APP_SUPPORTED_LOCALES` and delete its files, along with the tests that check it.
Kit works with a single locale.
