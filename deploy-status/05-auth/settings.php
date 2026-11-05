<?php

// Sets or clears an Auth setting in the development SQLite database for the screenshots:
//   php deploy-status/05-auth/settings.php captcha 1
//   php deploy-status/05-auth/settings.php captcha        (clears it: back to the default)
declare(strict_types=1);

[$script, $key, $value] = $argv + [null, null, null];
$db = new PDO('sqlite:' . __DIR__ . '/../../writable/database.db');
$db->prepare('DELETE FROM settings WHERE class = ? AND key = ?')->execute(['Config\Auth', $key]);

if ($value !== null) {
    $type = ctype_digit($value) ? 'integer' : 'string';
    $db->prepare('INSERT INTO settings (class, key, value, type, context, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, datetime(), datetime())')
        ->execute(['Config\Auth', $key, $value, $type]);
}
