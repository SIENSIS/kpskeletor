<?php

declare(strict_types=1);

namespace Modules\Contact\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Contact module (session 48, D-48-01, D-48-02, D-48-03): a public contact form
 * (the page `contact`, with the language prefix, and the section type `contact.form`) whose
 * messages are stored and handled in the back office, protected against spam by several layers
 * (honeypot, signed time trap, the Auth module's local captcha, rate limits, content rules) and
 * never usable to send mail to third parties (no auto-reply, the visitor only in `Reply-To`).
 *
 * Resources (permissions `contact-<resource>.<operation>`):
 * - `messages` (kpcrudy): list, show, delete; extras `answer` (mark answered), `archive` and
 *   `all` (see the messages of every recipient; without it a user sees only the messages
 *   addressed to the recipients whose group they belong to, a record rule);
 * - `recipients` (kpcrudy): the list of recipients offered by the form (name per language,
 *   addresses and/or a group to notify, order, active).
 */
final class Module extends ModuleManifest
{
    public string $name        = 'Contact';
    public string $version     = '0.1.0';
    public string $icon        = 'bi bi-envelope';
    public string $description = 'Contact.module.description';
    public string $author      = 'kpskeletor';
    public array $requires     = ['Core' => '^1.0', 'Auth' => '^0.2', 'Dashboard' => '^0.1'];

    public array $resources = [
        'messages' => [
            'label'  => 'Contact.res.messages',
            'type'   => 'crud',
            'except' => ['add', 'edit', 'export', 'import', 'trash', 'restore', 'purge'],
            'extra'  => [
                'answer'  => 'Contact.perm.answer',
                'archive' => 'Contact.perm.archive',
                'all'     => 'Contact.perm.all',
            ],
        ],
        'recipients' => [
            'label'  => 'Contact.res.recipients',
            'type'   => 'crud',
            'except' => ['export', 'import', 'trash', 'restore', 'purge'],
        ],
    ];

    public array $defaultGrants = [
        'admin'  => ['contact-messages.*', 'contact-recipients.*'],
        'editor' => ['contact-messages.list', 'contact-messages.show', 'contact-messages.answer', 'contact-messages.archive'],
    ];

    public array $menu = [
        ['resource' => 'messages', 'label' => 'Contact.menu.messages', 'icon' => 'bi bi-envelope', 'order' => 45],
        ['resource' => 'recipients', 'label' => 'Contact.menu.recipients', 'icon' => 'bi bi-person-lines-fill', 'order' => 1, 'parent' => 'messages'],
    ];

    /**
     * Settings › Contact (privacy page, retention) and Settings › Security › Contact (the spam
     * layers; weaker values are refused by their rules).
     */
    public array $settings = [
        'privacyPage' => [
            'type'    => 'string',
            'default' => '',
            'rules'   => 'permit_empty|max_length[255]|regex_match[/^\/(?!\/)\S*$/]',
            'label'   => 'Contact.settings.privacyPage',
            'help'    => 'Contact.settings.privacyPageHelp',
            'group'   => 'form',
        ],
        'retentionDays' => [
            'type'    => 'int',
            'default' => 365,
            'rules'   => 'required|integer|greater_than_equal_to[30]|less_than_equal_to[3650]',
            'label'   => 'Contact.settings.retentionDays',
            'help'    => 'Contact.settings.retentionDaysHelp',
            'group'   => 'form',
        ],
        'captcha' => [
            'type'    => 'select',
            'default' => 'always',
            'options' => ['always' => 'Contact.settings.captchaAlways', 'suspicious' => 'Contact.settings.captchaSuspicious'],
            'label'   => 'Contact.settings.captcha',
            'help'    => 'Contact.settings.captchaHelp',
            'group'   => 'security.contact',
        ],
        'rateLimit' => [
            'type'    => 'int',
            'default' => 3,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[20]',
            'label'   => 'Contact.settings.rateLimit',
            'help'    => 'Contact.settings.rateLimitHelp',
            'group'   => 'security.contact',
        ],
        'maxLinks' => [
            'type'    => 'int',
            'default' => 2,
            'rules'   => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[10]',
            'label'   => 'Contact.settings.maxLinks',
            'help'    => 'Contact.settings.maxLinksHelp',
            'group'   => 'security.contact',
        ],
        'blockedWords' => [
            'type'    => 'list',
            'default' => [],
            'label'   => 'Contact.settings.blockedWords',
            'help'    => 'Contact.settings.blockedWordsHelp',
            'group'   => 'security.contact',
        ],
        'latinScriptOnly' => [
            'type'    => 'bool',
            'default' => false,
            'label'   => 'Contact.settings.latinScriptOnly',
            'help'    => 'Contact.settings.latinScriptOnlyHelp',
            'group'   => 'security.contact',
        ],
    ];

    /** Dashboard widget: the new messages the user may read. */
    public array $widgets = [\Modules\Contact\Widgets\NewMessages::class];

    /** Retention: messages older than `Contact.retentionDays` are deleted every day. */
    public array $tasks = [\Modules\Contact\Tasks\PurgeMessages::class];

    public array $notifications = [\Modules\Contact\Notifications\MessageReceived::class];

    public array $sectionTypes = [\Modules\Contact\Sections\ContactFormSection::class];

    /** Only its tables (no files: the form takes no attachments). */
    public array $backup = [];

    /** No extra Content-Security-Policy sources (the captcha image is served by the site). */
    public array $csp = [];

    /** No outbound requests: no third-party captcha (D-48-02). */
    public array $outboundHosts = [];

    /** No API resources. */
    public array $api = [];
}
