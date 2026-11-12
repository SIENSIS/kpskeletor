# kpcrudy request: AJAX search for `setRelationNN()`

Request from kpskeletor session 24 (decision D-24-04; row in `docs/library-requests.md`). Ready to
be opened as an issue on `SIENSIS/kpcrudy` or implemented on its `main`. Written against kpcrudy
1.3.0 (`src/Crud.php`: `setRelation()` with `ajax`, `lookupOperation()`, `isLookup()`,
`canUseLookup()`, `relationBuilder()`, `relationText()`, `lookupData()`, `syncNN()`,
`selectedNN()`, `optionsFor()`; `src/Assets/kpcrudy.js`: `.kp-lookup`).

## Why

An N-N field (`setRelationNN()`) renders a `<select multiple>` with **every** record of the related
table (`optionsFor()` loads them all). For users, products or cities this does not scale: the
page is heavy, the list is unusable, and every record's text reaches the browser. kpskeletor's
event audience needs "a users field (searchable, AJAX)". `setRelation()` already has `ajax`
since 1.1; N-N fields need the same.

## API

```php
$crud->setRelationNN('users', 'event_audience', 'users', 'event_id', 'user_id', '{username} ({email})', [
    'where'    => ['active' => 1, 'deleted_at' => null],
    'order'    => 'username',
    'ajax'     => true,   // new: search box with suggestions instead of a <select multiple>
    'minChars' => 2,      // new: as setRelation()
    'limit'    => 20,     // new: as setRelation() (1-100)
    'max'      => null,   // new, optional: most values that may be chosen (null = no limit)
]);
```

Same option names, defaults and validation as `setRelation()` (`minChars` ≥ 1, `limit` 1–100).

## Behaviour

**Form (add and edit)**

- A text box (`data-kp-lookup-text`, the existing `.kp-lookup` widget) plus a list of the chosen
  values as removable chips; each chosen value is a hidden input `name="<field>[]"`.
- Choosing a suggestion adds a chip (no duplicates); the × of a chip removes it. Keyboard: arrows,
  Enter, Escape and Backspace on an empty box removes the last chip; `aria-*` like the 1-N widget.
- On the edit form only the texts of the **selected** values are loaded (one query with
  `whereIn(pk, selected)` through `relationBuilder()`), never the whole table.
- Without JavaScript: the hidden inputs keep the current values (the field cannot be changed, it
  is not lost).

**Lookup endpoint**

- The existing `GET base/lookup?field=<field>&q=<text>` (no new operation, no new route) also
  answers for N-N fields with `ajax`: `isLookup()` accepts `relationsNN`, `relationBuilder()`
  builds the query of the related table (`pk`, `display` fields, `where`, `order`).
- Same access rule as today (`canUseLookup()`): the field is in the add form (with `add`), the
  edit form (with `edit`) or the filters (with `list` + `filters`); otherwise 404. Hidden fields
  (`hideFields()`) never answer.
- `q` shorter than `minChars` → `{"results": []}`; at most `limit` results; `LIKE` escaped.
- Optional parameter `exclude[]=<id>` to leave out the values already chosen.

**Saving (unchanged rules)**

- Posted ids are kept only if they exist in the related table **and** match the relation's
  `where` (for `ajax`, checked with one `whereIn()` query instead of `optionsFor()`, which must
  not load the whole table).
- `syncNN()` keeps its contract: it rewrites only the junction rows of values the user could
  choose, and keeps links to records outside them. With `ajax` "could choose" = matches the
  relation's `where`; it must not depend on the list being loaded. kpskeletor relies on this
  (event groups another user set, `event_audience` with two N-N fields on one table).
- `max`, when given: more values → validation error on the field.
- An invalid id (not found, outside the `where`) → validation error "invalid option", not silently
  dropped (the 1-N `ajax` field already reports it; N-N without `ajax` keeps today's behaviour).

**Filters**

- The filter of an N-N `ajax` field is a single search box (one value, as the 1-N filter):
  "records linked to this value". `?f[<field>]=<id>` as today.

**List, details, export, import**

- Unchanged (the list and the detail view already show the linked texts with `attachNN()`).
- CSV import: values by id or text, as today, but matched with a query (`whereIn` / `where text =`)
  instead of `optionsFor()`; "matches more than one record" stays an error.

**Describe and translations**

- `describe()` reports `'ajax' => true` for the N-N relation (like 1-N).
- New texts in `ca`, `es` and `en`: chip remove label ("Remove {0}"), "No results", and the
  `max` error.

## Tests (to add in kpcrudy)

1. The form of an N-N `ajax` field renders no `<option>` of the related table, only the selected
   values with their texts (one query).
2. `lookup` answers for N-N `ajax` with the relation's `where` and `order`, `minChars`, `limit`,
   `exclude[]`; 404 without `add`/`edit`/`filters`, for a hidden field, for an N-N field without
   `ajax`, and for POST.
3. Saving: ids outside the `where`, unknown ids and non-numeric values are refused with a field
   error; links to records outside the `where` are kept; `max` enforced.
4. Two N-N fields on the same junction table with nullable columns (the kpskeletor pattern) do
   not touch each other's rows.
5. Filter by one value; CSV import by id and by text without loading the whole table.
6. Without `ajax`, nothing changes (regression).

## After the release, in kpskeletor

- `modules/Events/Controllers/Events.php`: `setRelationNN('users', …, ['ajax' => true, …])`
  (display `{username}`), raise the kpcrudy requirement in `composer.json`, test that the form
  lists no users, and close D-24-04 and the row in `docs/library-requests.md`.
