# Contact module

**Purpose:** a public contact form protected against spam whose messages are stored and handled
in the back office, without turning the site into a mail relay (session 48; D-48-01, D-48-02,
D-48-03). Full description: `docs/contact.md`.

## Resources and permissions

- `contact-messages` (crud − add, edit, export, import, trash, restore, purge; extras `answer`,
  `archive`, `all`): `backend/contact/messages`. Record rule (`Services/MessageAccess.php`):
  without `contact-messages.all` a user sees only the messages addressed to the recipients whose
  group they belong to (list, details, delete, operations, the widget).
- `contact-recipients` (crud − export, import, trash, restore, purge): `backend/contact/recipients`.
- Grants: admin `contact-messages.*`, `contact-recipients.*`; editor `contact-messages.{list,show,answer,archive}`.
- Menu "Contact messages" (sidebar, 45) with "Recipients" under it.
- Settings: `Contact.privacyPage`, `Contact.retentionDays` (group `form`); `Contact.captcha`,
  `Contact.rateLimit`, `Contact.maxLinks`, `Contact.blockedWords`, `Contact.latinScriptOnly`
  (Settings › Security, group `security.contact`).

## Main pieces

- Public: `Controllers/ContactPage.php` (`GET/POST contact`, `GET contact/captcha`), views
  `Views/form.php` (shared), `Views/public/contact.php`; section `Sections/ContactFormSection.php`
  (`contact.form`, view `Views/sections/form.php`, thumbnail `Assets/img/sections/form.svg`).
- Spam layers: `Services/FormToken.php` (signed time trap + recipients offered + return path),
  honeypot input `website`, Auth's `Captcha` (always or when suspicious, `Services/ContactForm.php`),
  `Services/ContentRules.php` (control characters, links, blocked words, script),
  `Services/SenderHistory.php` (rate limits per IP/email through the Core `RateLimiter`, hashed
  history in the cache). Refusals: `security_log('Contact', 'message_refused')` with the reason.
- `Services/ContactMessages.php` (store, notify the group's readers, queue emails to the
  recipient's addresses with the visitor only in `Reply-To`, mark, purge),
  `Services/Recipients.php`; `Notifications/MessageReceived.php`; `Tasks/PurgeMessages.php`
  (daily 03:40); `Widgets/NewMessages.php` (`contact.new`).
- Back office: `Controllers/Messages.php`, `Controllers/Recipients.php` (kpcrudy).
- Tables: `contact_recipients`, `contact_recipient_translations`, `contact_messages` (no IP).
- Core change for this module: `MailQueue::push(..., $replyTo)` and `mail_queue.reply_to` (D-48-03).

## Decisions

D-48-01 and D-48-02 decided by the owner; D-48-03 (the `all` permission, extra settings, Reply-To
in the Core's mail queue) — see `docs/decisions.md`.

## Open tasks

- A refused or invalid message sent from a section is shown again on the contact page (not on
  the page that holds the section).
- One captcha challenge per session: two forms with captcha open in two tabs invalidate each other.

## How to test

`vendor/bin/phpunit modules/Contact` (base `Tests/ContactTestCase.php`). By hand:
`php spark modules:demo Contact`, then `/contact`, `/escriu-nos` (Pages demo) and
`backend/contact/messages`, `backend/contact/recipients`. E2E: `tests/e2e/contact.spec.mjs`.
