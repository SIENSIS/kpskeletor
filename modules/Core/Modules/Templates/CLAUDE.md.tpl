# {{Name}} module

> Read this file first when you resume work on this module, and keep it current (root `CLAUDE.md`,
> "Developing a module"). The project rules are in the root `CLAUDE.md`; the steps in
> `docs/creating-a-module.md`; run `/check-module {{Name}}` before calling the module done.

**Purpose:** TODO: what the module manages, for whom, and what it adds to the back office, the
public site and the API.

## Resources and permissions

Permissions are `{{name}}-<resource>.<operation>`, generated from `Config/Module.php` (never
written by hand).

- `{{name}}-main` (page: `view`): the module's screen `<back office>/{{name}}` (`backend_path('{{name}}')`).{{crudClaude}}{{apiClaude}}
- Default grants: admin `{{name}}-main.*`{{crudGrantClaude}}.
- Settings: `{{Name}}.perPage`. Widget `{{name}}.main` (`Widgets/{{Name}}Widget.php`). Scheduled
  task `Tasks/{{Name}}Task.php`.
- Tables: `{{table}}` (one migration per table, `down()` implemented).

## Main pieces

- `Config/Module.php` (manifest), `Config/Routes.php` (every back-office route has its `can:`
  filter{{apiRouteClaude}}).
- `Controllers/Home.php` + `Views/index.php` (example screen with the ES-module component
  `Assets/js/{{name}}.js`, `data-component="{{name}}/{{name}}"`).
- `Database/Seeds/{{Name}}Seeder.php` (initial data), `{{Name}}DemoSeeder.php` (demo data:
  `php spark modules:demo {{Name}}`).
- Texts: `Language/{ca,es,en}/{{Name}}.php` (same keys).

## Decisions

- None yet. Record module decisions here, and project-wide ones in `docs/decisions.md`.

## Open tasks

- [ ] Describe the purpose above and replace the example screen, widget and scheduled task.
- [ ] Complete the manifest: resources, menus, default grants, settings, `$requires`, `$backup`,
      `$csp`, `$outboundHosts`, `$api`.
- [ ] Tests: permissions (including forged POSTs), migration up/down/up, API scopes, the
      module's own rules.
- [ ] Documentation (`docs/` or this file) and `/check-module {{Name}}`.

## How to test

```bash
php spark modules:sync              # namespace, migrations, permissions, settings, assets
php spark permissions:check
vendor/bin/phpunit modules/{{Name}}
```

By hand: `php spark modules:demo {{Name}}`, then `<back office>/{{name}}` (`backend_path('{{name}}')`) with a user of the `admin` group.
