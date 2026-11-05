# Library requests

Add a row here whenever something cannot be done with the public API of `siensis/kpcrudy` or
`siensis/kpapy`: never edit `vendor/` or copy their code, write the temporary solution in the
Core and record it below so it can be added to the library.

Gaps found while reviewing kpcrudy 1.0.0 and kpapy 1.0.0 for `docs/plan.md`:

| Library | Missing feature | Why it is needed | Workaround | Status |
|---|---|---|---|---|
| kpcrudy | Pluggable storage for `upload`/`image` fields (a storage adapter or upload-handler callback, MIME check, image re-encoding, private disk) | Specification "Storage" and "CRUDs with kpcrudy": uploads must go through the Core Storage service (allow-list + MIME, re-encoding, SVG refused, `files` metadata); kpcrudy writes to `uploadPath` (default `public/uploads`) itself | Do not use kpcrudy upload field types; use the Core file-picker field via `callbackField()` and save through the Storage service | Open |
| kpapy | Pluggable storage for upload fields (same as above) | Same rules apply to files uploaded through the API; kpapy writes to `writable/uploads/kpapy` | No API upload fields in core resources; a custom operation that calls the Storage service when needed | Open |
| kpcrudy | Option to load the CRUD's JavaScript and CSS as external asset files instead of nonce'd inline `<script>`/`<style>` blocks | Specification "Front-end assets": no inline `<script>` code in the HTML | Accept the library's nonce'd blocks as the only exception (CSP still enforced); CI inline-code check scoped to project views | Open |
| kpcrudy | Locale fallback for `setTranslations()` in lists, search, sorting and export (like kpapy's `localeFallbacks`) | Lists show an empty cell when a record has no translation in the list locale; demo data is Catalan only, and the spec requires a fallback chain | Use `listLocale` = `ca` (required language) unless the content is known to be complete in the user's locale | Open |
| kpcrudy | Translated fields in CSV import | Events administration requires CSV import, and event titles are translated | `callbackImport()` writes the Catalan translation row | Open |
| kpcrudy | Responsive list options: priority columns hidden on phones, card view on phones, row actions in a "⋯" menu, collapsible filters panel | Specification "Responsive design" (kpcrudy lists) | Theme CSS on the `bootstrap5` markup and a Core JS component; no template copies | Open |
| kpcrudy | Searchable (AJAX) relation fields for large tables | Events audience "users field (searchable, AJAX)"; a dropdown of every user does not scale | `callbackField()` with a Core `data-component` that queries a permission-checked JSON endpoint | Open |
| kpcrudy | Tree display for self-referencing tables (indentation/order by parent) | Pages "list with tree indentation" | `callbackColumn()` prefixing the depth and a `defaultOrder()` on a materialised path column | Open |

Found in session 09 (kpcrudy integration):

| Library | Missing feature | Why it is needed | Workaround | Status |
|---|---|---|---|---|
| kpcrudy | `kpcrudy:make` options passed in `$params` (it reads `CLI::getOption()` only) | `make:module --crud` runs it from another command; `command()` passes options in `$params` | `make:module` runs `php spark kpcrudy:make …` as a child process | Open |
| kpcrudy | A way to describe a CRUD's operations without running the controller action | `permissions:check` compares every CRUD with the catalogue | `CrudAuditor` runs the action with `CrudFactory::inspect()` and stops at `renderCrud()` | Open |
| kpcrudy | `Crud` accepting the CLI request (it requires `IncomingRequest`) | Building a CRUD from `spark` (`permissions:check`) | `CrudAuditor` injects a GET `IncomingRequest` while inspecting | Open |
| kpcrudy | Upload fields: `uploadPath` set to `writable/kpcrudy-unused-uploads` (session 09) | See the first row above | `make:module --crud` replaces guessed `image`/`upload` field types with a comment | Open |
