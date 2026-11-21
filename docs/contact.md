# Contact

The **Contact** module (session 48; decisions D-48-01, D-48-02, D-48-03) gives the public site a
contact form whose messages are stored and handled in the back office. It is built to resist spam
and so that **nothing in it can be used to send mail to third parties**.

## For visitors

- **Page `contact`** (`/contact`, `/es/contact`, `/en/contact`: the language prefix of
  `LocaleUrls`) and the **section "Contact form"** (`contact.form`) that editors can put in any
  page built from sections (the Pages demo has it in "Escriu-nos", `/escriu-nos`).
- Fields: name, email, **recipient** (a list kept by the administrator; their addresses never
  appear on the site), subject, message, a required **privacy** checkbox (linked to
  `Contact.privacyPage` when set) and the security question (captcha).
- After sending, the visitor goes back to the page of the form with a confirmation. A form that
  must be corrected is shown again on `contact` with the values typed.
- **Nobody writes back automatically.** The visitor gets no copy or receipt: an automatic reply
  could be abused to send mail to any address typed in the form.

## For the back office

| Screen | URL | Permission |
|---|---|---|
| Contact messages | `backend/contact/messages` | `contact-messages.list` (+ `show`, `delete`, `answer`, `archive`) |
| Recipients | `backend/contact/recipients` | `contact-recipients.*` |

- **Messages**: list (subject, name, recipient, status, date), details (the message as typed,
  escaped), **Reply by email** (opens your own mail program addressed to the visitor), **Mark
  answered** (`answer`), **Archive** (`archive`), **Delete**. Statuses `new`, `answered`,
  `archived`. Messages are never edited.
- **Who reads what** (record rule, `Services/MessageAccess.php`): holders of
  `contact-messages.all` read every message; anyone else with `contact-messages.list` reads only
  the messages addressed to the recipients **whose group they belong to**. A forged id of another
  message answers like a missing record (details, operations, delete).
- **Recipients**: name per language (shown on the form), **addresses** (up to five, one per
  line; each message is emailed to them), **group** (its members who may read messages are
  notified and read the recipient's messages), order, and "offered on the form".
- **Notification** "New contact message" (`MessageReceived`): to the members of the recipient's
  group who hold `contact-messages.show` (bell, email or push per their preferences, queued). A
  recipient with neither a group nor addresses notifies the holders of `contact-messages.all`,
  so no message is lost.
- **Dashboard widget** "New contact messages" (`contact.new`): the newest `new` messages the user
  may read.
- Default grants: admin `contact-messages.*`, `contact-recipients.*`; editor
  `contact-messages.{list,show,answer,archive}` (no `all`, no delete).

## Spam layers

Each layer refuses with a **generic message** ("Your message could not be sent…") and writes a
`security_log('Contact', 'message_refused')` entry whose `changes.reason` names it (plus a short
hash of the typed email, never the address). Order:

| # | Layer | Reason | Setting |
|---|---|---|---|
| 1 | **Signed time trap** (`FormToken`): an HMAC token (key derived from the app key, context `contact.form`) with the time the form was shown, the recipients it offered and the path to go back to. Refused: sent less than 3 s after (robots), more than 2 h after (stale or replayed), missing or altered | `too_fast`, `expired`, `token_invalid` | — |
| 2 | **Honeypot**: an input named `website`, hidden from people (visually hidden, `aria-hidden`, out of the tab order); robots fill it | `honeypot` | — |
| 3 | **Captcha**: the Auth module's local arithmetic captcha (image at `contact/captcha`, HMAC of the answer in the session, single use) | `captcha` (the visitor sees "wrong answer") | `Contact.captcha`: `always` (default) or `suspicious` (only after a message or a refusal from the same IP or email within the hour, or after a refusal in the session) |
| 4 | **Recipient**: must be active and among those the form offered (the list is in the signed token, so a section restricted to one recipient cannot be used for another) | `recipient` | — |
| 5 | **Content rules** (`ContentRules`): no control characters or line breaks in name, email and subject (header injection); at most N links (`http(s)://`, `www.`, `<a`, `[url`); no blocked word (capitals and accents ignored); optionally no message written mostly (> 30 %) in a non-Latin script | `header_injection`, `too_many_links`, `blocked_word`, `script` | `Contact.maxLinks` (2), `Contact.blockedWords`, `Contact.latinScriptOnly` (off) |
| 6 | **Rate limits** (`SenderHistory`, Core `RateLimiter`): N messages per hour per IP **and** per email; answers 429 with `Retry-After` | `rate_limit` | `Contact.rateLimit` (3) |

The POST route also has the Core's `ratelimit:forms` filter (10 attempts an hour per IP for
guests) and CSRF. Validation mistakes (a missing field, a bad address, the privacy box) are shown
as field errors and are not logged. **No third-party captcha** (D-48-02): it would share
visitors' data with another company and need an outbound request during the request.

## Mail safety

- Emails to a recipient's addresses are **queued** (`mail_queue`, sent by the scheduler) **from
  the site's address** (`Email.fromEmail`); the visitor's address goes only in **`Reply-To`**,
  validated again by `MailQueue` (one address, no control characters; otherwise the email is
  not queued and never sent with it). The subject is `[Site] Contact: <subject>` with line
  breaks removed; the body escapes everything typed.
- No auto-reply, no copy to the visitor, no attachments (the form has no file input).
- Notifications to users never carry the visitor's address (id, subject, names).

## Data protection

- Messages are **deleted after `Contact.retentionDays` days** (365 by default, never fewer than
  30) by the scheduled task "Delete old contact messages" (`PurgeMessages`, every day at 03:40;
  an activity entry when it deletes something).
- **No IP address is stored** with a message. Rate limits and the "suspicious" history use cache
  counters keyed by a SHA-256 of the IP or email, which expire after one hour.
- The form says what the data are used for and for how long; `Contact.privacyPage` links the
  site's privacy page (an internal path such as `/privacitat`).

## Settings

| Setting | Where | Default | |
|---|---|---|---|
| `Contact.privacyPage` | Settings › Contact | empty | internal path of the privacy page |
| `Contact.retentionDays` | Settings › Contact | 365 | 30–3650 |
| `Contact.captcha` | Settings › Security › Contact form (spam) | `always` | `always` or `suspicious` |
| `Contact.rateLimit` | same | 3 | messages per hour per IP and per email, 1–20 |
| `Contact.maxLinks` | same | 2 | 0–10 |
| `Contact.blockedWords` | same | none | one per line |
| `Contact.latinScriptOnly` | same | off | |

## Tables

`contact_recipients` (emails, group_name, position, active), `contact_recipient_translations`
(name per language), `contact_messages` (recipient, a copy of the recipient's name, name, email,
subject, message, locale, status, answered_at/by, timestamps). The Core's `mail_queue` gained a
`reply_to` column (D-48-03).

## Testing

`vendor/bin/phpunit modules/Contact`: every spam layer, forged recipients, header injection,
CSRF, notifications and mail (Reply-To, nothing to the visitor), escaping in the back office and
in the email, the record rule and operations, the widget, the retention task, migrations
up/down/up. E2E: `tests/e2e/contact.spec.mjs` (the page and the section, phone, desktop and
dark, axe, no CSP violation). By hand: `php spark modules:demo Contact`, `/contact`,
`/escriu-nos`, `backend/contact/messages`.
