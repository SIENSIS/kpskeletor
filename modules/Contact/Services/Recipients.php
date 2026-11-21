<?php

declare(strict_types=1);

namespace Modules\Contact\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Content\ContentTranslations;

/**
 * The recipients of the contact form (`contact_recipients`): the active ones offered to visitors
 * (names only, never their addresses), their addresses and group, and the recipients whose
 * messages a user may read (the record rule of `contact-messages`).
 */
final class Recipients
{
    /** At most this many addresses per recipient. */
    public const MAX_EMAILS = 5;

    /**
     * The active recipients in order, named in a language; `$only` keeps those ids ([] = all).
     *
     * @param list<int> $only
     *
     * @return list<array{id: int, name: string}>
     */
    public function active(?string $locale = null, array $only = []): array
    {
        $builder = db_connect()->table('contact_recipients')->select('id')->where('active', 1)->orderBy('position')->orderBy('id');

        if ($only !== []) {
            $builder->whereIn('id', $only);
        }

        $ids   = array_map('intval', array_column($builder->get()->getResultArray(), 'id'));
        $names = ContentTranslations::texts('contact_recipient_translations', 'recipient_id', $ids, ['name'], $locale);
        $list  = [];

        foreach ($ids as $id) {
            $list[] = ['id' => $id, 'name' => $names[$id]['name'] ?? ('#' . $id)];
        }

        return $list;
    }

    /**
     * Every recipient (active or not), id => name, for the back office.
     *
     * @return array<int, string>
     */
    public function names(?string $locale = null): array
    {
        $ids   = array_map('intval', array_column(db_connect()->table('contact_recipients')->select('id')->orderBy('position')->orderBy('id')->get()->getResultArray(), 'id'));
        $names = ContentTranslations::texts('contact_recipient_translations', 'recipient_id', $ids, ['name'], $locale);
        $list  = [];

        foreach ($ids as $id) {
            $list[$id] = $names[$id]['name'] ?? ('#' . $id);
        }

        return $list;
    }

    /**
     * @return array{id: int, emails: list<string>, group_name: string|null, active: bool}|null
     */
    public function find(int $id): ?array
    {
        $row = db_connect()->table('contact_recipients')->where('id', $id)->get()->getRowArray();

        if (! is_array($row)) {
            return null;
        }

        return [
            'id'         => (int) $row['id'],
            'emails'     => self::parseEmails((string) ($row['emails'] ?? '')),
            'group_name' => ($row['group_name'] ?? '') === '' ? null : (string) $row['group_name'],
            'active'     => (int) $row['active'] === 1,
        ];
    }

    /**
     * The ids of the recipients whose group the user belongs to.
     *
     * @return list<int>
     */
    public function readableBy(User $user): array
    {
        $groups = $user->getGroups() ?? [];

        if ($groups === []) {
            return [];
        }

        return array_map('intval', array_column(db_connect()->table('contact_recipients')->select('id')->whereIn('group_name', $groups)->get()->getResultArray(), 'id'));
    }

    /**
     * The addresses of a stored list (one per line, or separated by commas or semicolons), valid
     * ones only, without duplicates.
     *
     * @return list<string>
     */
    public static function parseEmails(string $value): array
    {
        $emails = [];

        foreach (preg_split('/[\s,;]+/', $value) ?: [] as $email) {
            $email = mb_strtolower(trim($email));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && ! in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * Validation of the addresses field: empty, or up to MAX_EMAILS valid addresses.
     */
    public static function emailsError(string $value): ?string
    {
        $parts = array_values(array_filter(preg_split('/[\s,;]+/', trim($value)) ?: [], static fn (string $part): bool => $part !== ''));

        foreach ($parts as $part) {
            if (filter_var($part, FILTER_VALIDATE_EMAIL) === false) {
                return lang('Contact.errors.emailInvalid', [$part]);
            }
        }

        if (count($parts) > self::MAX_EMAILS) {
            return lang('Contact.errors.tooManyEmails', [self::MAX_EMAILS]);
        }

        return null;
    }
}
