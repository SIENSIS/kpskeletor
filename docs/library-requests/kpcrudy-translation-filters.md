# kpcrudy request: `filters` for translated fields

Request from kpskeletor (decision D-17-02, session 24 review; row "before* hooks that can change
translated values" in `docs/library-requests.md`). Implemented on kpcrudy's `main` for 1.4.

## Why

Translated fields (`setTranslations()`) live in a child table; the `before*` hooks only receive
the main table's data and `postedTranslations()` is read-only. Rich text (News and Pages bodies,
Events descriptions) must be sanitised with HTMLPurifier **before it is stored**, so kpskeletor
rewrites `kp_tr[*][field]` in the request before the CRUD reads it. That workaround:

- is copied in every module with rich text (News, Pages, Events);
- does **not** reach the CSV import: kpcrudy reads translated columns from the file and stores them
  as they come (`callbackImport()` does not apply to translated fields), so Events had to leave its
  description out of the import.

## API

```php
$crud->setTranslations('news_translations', 'news_id', 'locale', ['title', 'body'], $locales, [
    'filters' => [
        'body'  => static fn (string $value, string $locale): string => HtmlSanitizer::clean($value),
        'title' => static fn (string $value): string => preg_replace('/\s+/u', ' ', $value),
    ],
]);
```

- `filters`: `[field => Closure(string $value, string $locale): string]`, only for fields of
  `$fields`; anything else (an unknown field, a value that is not a `Closure`) throws a
  `RuntimeException` when `setTranslations()` is called (a configuration error, like
  `translationsEmpty`).

## Behaviour

- Applied to every **non-empty** value of the field, in every language, **before validation**, in:
  - the add and edit forms (the values `readPostedTranslations()` reads);
  - the CSV import (the values of the file's translated columns; languages and fields not in
    the file keep their stored value and are not filtered again).
- The result is trimmed, validated with the field's rules and stored; if the form is shown again
  (validation errors), it shows the filtered values.
- `postedTranslations()` returns the filtered values, so `beforeInsert` / `beforeUpdate` hooks see
  what will be stored.
- A filter that returns an empty string leaves the language empty (an optional language is then
  removed, a required one fails validation), as if the user had left it empty.
- Nothing changes for fields without a filter, nor for lists, details, export or the API of
  `describe()` (which reports the names of the filtered fields in `translations.filters`).

## Tests (in kpcrudy)

1. A form value is filtered before validation and storage (and per locale: the closure gets the
   locale); the redisplayed form shows the filtered value; `postedTranslations()` in a hook sees it.
2. The CSV import filters the translated columns of the file and leaves other languages alone.
3. A filter that empties a required language makes validation fail.
4. An unknown field or a non-Closure filter throws.

## After the release, in kpskeletor

- News, Pages and Events: replace the request rewrite (`sanitizePostedBody()` /
  `sanitizePostedDescription()`) with `'filters' => ['body' => fn (string $v): string => HtmlSanitizer::clean($v)]`
  (keep the clean-up on output); Events can import its description again; tests for the import.
