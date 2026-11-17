# {{Name}} module

A module for the kpskeletor application skeleton (CodeIgniter 4). TODO: describe what it does.

## Requirements

- A kpskeletor installation whose Core matches `{{coreConstraint}}` (see `Config/Module.php`,
  `$requires`).

## Installing

- **As a package:** build the ZIP of this folder (a single top-level folder `{{Name}}/`) and install it
  on the site with `php spark modules:install {{Name}}.zip`, or upload it in Modules › Packages and run
  `php spark modules:install --pending` (docs/installing-modules.md of the skeleton).
- **For development:** clone this repository into `modules/{{Name}}/` of a skeleton checkout (or add it
  as a git submodule) and run `php spark modules:sync`.

## Developing

Work inside a checkout of the skeleton so its `CLAUDE.md`, commands and tests apply:

```bash
git clone <skeleton repository> site && cd site
git clone <this repository> modules/{{Name}}
composer install
php spark modules:sync
vendor/bin/phpunit modules/{{Name}}
```

See `CLAUDE.md` in this folder for the module's own notes and the skeleton's rules.
