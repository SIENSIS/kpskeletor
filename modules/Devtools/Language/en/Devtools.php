<?php

declare(strict_types=1);

return [
    'title'      => 'Development tools',
    'intro'      => 'Tools that help to build modules. They exist only in the development environment, from this computer, for superadmins.',
    'banner'     => 'Development tools: never enabled in production.',
    'bannerHelp' => 'They answer only on this computer (http://localhost) while CI_ENVIRONMENT is development.',
    'module'     => ['description' => 'Development-only tools: CRUD recipes, and later the code generator and the package builder.'],
    'menu'       => ['tools' => 'Development tools'],
    'res'        => ['tools' => 'Development tools'],
    'perm'       => ['write' => 'Write files and run commands from the development tools'],
    'locks'      => [
        'title'       => 'Why it is safe',
        'environment' => 'Its routes exist only when CI_ENVIRONMENT is development: anywhere else every Devtools address answers 404.',
        'manifest'    => 'It is a development-only module: it cannot be enabled or installed outside development, and a modules:sync there disables it.',
        'check'       => 'security:check reports an error when it is enabled outside development.',
        'local'       => 'It answers only requests from this computer (loopback address and a localhost Host); use php spark serve.',
        'permission'  => 'Only superadmins (devtools.view, devtools.write granted to nobody); anything that writes asks for your password again.',
        'packaging'   => 'It is part of the repository, so it is in release archives, but the locks above keep it inert.',
    ],
    'recipes' => [
        'title'    => 'CRUD recipes',
        'intro'    => 'Every variant of a back-office CRUD and its API, with real code from the skeleton and the documentation it comes from.',
        'contents' => 'Recipes',
        'what'     => 'What it is',
        'when'     => 'When to use it',
        'source'   => 'From',
        'copy'     => 'Copy',
        'copied'   => 'Copied',
        'more'     => 'More recipes: vendor/siensis/kpcrudy/docs/howto_EN.md (kpcrudy) and vendor/siensis/kpapy/docs/howto_EN.md (kpapy).',
        'plain'    => [
            'title' => 'Plain CRUD',
            'what'  => 'A list with search, filters, details and forms over a table: CrudFactory::routes() in the routes, $this->crud() and $this->renderCrud() in a BackendController.',
            'when'  => 'Every back-office screen that lists and edits records. Start with php spark make:module <Name> --crud=<table>.',
        ],
        'readonly' => [
            'title' => 'Read-only CRUD',
            'what'  => 'The resource declares only list and show ("except" in the manifest) and the controller switches off the rest with unset*().',
            'when'  => 'Logs, histories, records written by code (activity log, notifications, tokens).',
        ],
        'operation' => [
            'title' => 'Custom operation',
            'what'  => 'A button on each record (and a bulk action) that runs your code, with its own permission declared in "extra".',
            'when'  => 'Actions that are not add/edit/delete: complete, publish, accept, resend…',
        ],
        'reauth' => [
            'title' => 'Operations behind re-authentication',
            'what'  => 'The reauth filter asks for the password again before the route runs; add ratelimit:forms to writes.',
            'when'  => 'Sensitive actions: restoring backups, downloading data, managing keys or other users\' security.',
        ],
        'recordRules' => [
            'title' => 'Record rules',
            'what'  => 'setPermission() with a closure decides per record on top of the role\'s permission (e.g. only the author edits).',
            'when'  => 'Everybody sees the records but only some may change some of them.',
        ],
        'ownRows' => [
            'title' => 'Only own records',
            'what'  => 'where() limits the list, details, edits, deletes, bulk actions and export; a forged id is "not found".',
            'when'  => 'Personal screens ("my posts", "my activity"): their own resource granted to every group.',
        ],
        'hiddenFields' => [
            'title' => 'Fields hidden by permission',
            'what'  => 'hideFields() after checking an extra permission removes a field from every view, the export and the import.',
            'when'  => 'Sensitive columns: estimates, internal notes, IP addresses, authors.',
        ],
        'translations' => [
            'title' => 'Translated fields',
            'what'  => 'setTranslations() stores the fields per language in a translations table; ContentTranslations gives the site\'s languages and requires the default one.',
            'when'  => 'Content shown to visitors in several languages (names, titles, descriptions).',
        ],
        'richText' => [
            'title' => 'Rich text',
            'what'  => 'The Core\'s editor on a textarea (RichTextEditor::attrs(), the attrs option of a translated field) and HtmlSanitizer before storing and when showing.',
            'when'  => 'Bodies of pages, news and events: formatted text with links and images.',
        ],
        'uploads' => [
            'title' => 'Uploads and the file picker',
            'what'  => 'A CrudUploadHandler subclass renders the Core\'s file picker and stores through the Storage service (allow-lists, random names).',
            'when'  => 'Attachments, covers and images of a record. Never kpcrudy\'s upload fields on their own.',
        ],
        'export' => [
            'title' => 'Export and import',
            'what'  => 'CSV export of the chosen columns (raw values with callbackExport()) and import, each with its own permission.',
            'when'  => 'Data that people take to a spreadsheet or load in bulk.',
        ],
        'trash' => [
            'title' => 'Trash',
            'what'  => 'A deleted_at column turns delete into "move to trash" with restore and purge; switch them off when not declared.',
            'when'  => 'Records people may delete by mistake (content, to-do tasks).',
        ],
        'maskedIds' => [
            'title' => 'Masked ids',
            'what'  => 'Every CRUD shows codes instead of ids (app.maskKey, shared with kpapy). They hide the numbering; they are not access control.',
            'when'  => 'Always on. Keep the permissions and record rules anyway.',
        ],
        'activity' => [
            'title' => 'Activity log',
            'what'  => 'Writes, operations, bulk actions, imports and exports are logged by the Core (AuditedCrud); skipActivity() leaves out view-only operations.',
            'when'  => 'Nothing to do for a CRUD; activity_log() for other actions.',
        ],
        'api' => [
            'title' => 'API resource',
            'what'  => 'A kpapy Resource under api/v1/<path> with the kpapy filter, declared in $api, with the same resource name as the CRUD: tokens use the same permissions.',
            'when'  => 'Apps and integrations that read or change the same records.',
        ],
        'menu' => [
            'title' => 'Menu entry and default grants',
            'what'  => 'The manifest\'s $menu (resource, zone, parent, order) and $defaultGrants (applied once on install).',
            'when'  => 'Every new resource: who gets it and where it appears.',
        ],
    ],
];
