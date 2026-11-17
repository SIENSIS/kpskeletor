<?php

declare(strict_types=1);

namespace Modules\Users\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;
use CodeIgniter\I18n\Time;

/**
 * D-25-10 (session 31): the stored greetings `Users.birthdayGreetingCa|Es|En` become one
 * translated setting `Users.birthdayGreeting` (locale => text, stored by codeigniter4/settings
 * as a serialized array). No table changes: only rows of the settings table. down() splits it
 * again (locales other than ca/es/en are lost).
 */
final class MergeBirthdayGreetingSettings extends Migration
{
    private const OLD = ['ca' => 'birthdayGreetingCa', 'es' => 'birthdayGreetingEs', 'en' => 'birthdayGreetingEn'];

    private const NEW = 'birthdayGreeting';

    public function up(): void
    {
        if (! $this->settingsTableExists()) {
            return;
        }

        $groups = [];

        foreach ($this->rows(array_values(self::OLD)) as $row) {
            $locale = array_search($row['key'], self::OLD, true);
            $text   = is_string($row['value']) ? trim($row['value']) : '';

            $groups[$this->groupKey($row)]['row'] = $row;

            if (is_string($locale) && $text !== '') {
                $groups[$this->groupKey($row)]['texts'][$locale] = $text;
            }
        }

        foreach ($groups as $group) {
            $texts = $group['texts'] ?? [];
            ksort($texts);

            if ($texts !== []) {
                $this->store($group['row']['class'], $group['row']['context'], self::NEW, serialize($texts), 'array');
            }
        }

        $this->db->table($this->table())->whereIn('key', array_values(self::OLD))->where('class', $this->className())->delete();
    }

    public function down(): void
    {
        if (! $this->settingsTableExists()) {
            return;
        }

        foreach ($this->rows([self::NEW]) as $row) {
            $texts = $row['type'] === 'array' && is_string($row['value']) ? @unserialize($row['value'], ['allowed_classes' => false]) : null;

            foreach (is_array($texts) ? $texts : [] as $locale => $text) {
                if (isset(self::OLD[$locale]) && is_string($text) && $text !== '') {
                    $this->store($row['class'], $row['context'], self::OLD[$locale], $text, 'string');
                }
            }
        }

        $this->db->table($this->table())->where('key', self::NEW)->where('class', $this->className())->delete();
    }

    /**
     * @param list<string> $keys
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $keys): array
    {
        return $this->db->table($this->table())->whereIn('key', $keys)->where('class', $this->className())->get()->getResultArray();
    }

    private function store(string $class, ?string $context, string $key, string $value, string $type): void
    {
        $now = Time::now()->format('Y-m-d H:i:s');

        $this->db->table($this->table())->insert([
            'class'      => $class,
            'key'        => $key,
            'value'      => $value,
            'type'       => $type,
            'context'    => $context,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function groupKey(array $row): string
    {
        return (string) ($row['context'] ?? "\0");
    }

    /** The class codeigniter4/settings stores `Users.*` under (there is no `Config\Users` class). */
    private function className(): string
    {
        return 'Users';
    }

    private function settingsTableExists(): bool
    {
        return $this->db instanceof BaseConnection && $this->db->tableExists($this->table());
    }

    private function table(): string
    {
        $table = config('Settings')->database['table'] ?? 'settings';

        return is_string($table) ? $table : 'settings';
    }
}
