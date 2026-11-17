# User guide (back office)

**Languages:** English · [Català](ca/user-guide.md) · [Español](es/user-guide.md)

This guide is for the people who use the back office: signing in, your profile and security,
the dashboard, notifications, files, content (pages, menus, news), the calendar, to-do tasks,
your activity and personal API tokens. Developers and administrators: see the other documents in
`docs/` (for example [`permissions.md`](permissions.md), [`auth.md`](auth.md),
[`calendar.md`](calendar.md) and [`files.md`](files.md)).

## Before you start

- **What you see depends on your permissions.** Administrators give permissions to groups (for
  example `admin`, `editor`, `user`), and each user belongs to one or more groups. The menu only
  shows the screens you may open, and buttons you may not use are not shown. If you miss a
  screen described here, ask an administrator. Some modules can also be turned off (the to-do
  tasks module is off by default).
- **Addresses.** Paths such as `/backend/profile` are relative to the address of your site (for
  example `https://example.org/backend/profile`). The back office starts at `/backend`.
- **Where things are.** The back office has three places with menu entries:
  - the **sidebar** on the left: the main screens (Dashboard, News, Pages, Calendar, Menus,
    Files, To-do tasks…), and under the **Settings** heading the administration screens;
  - the **user menu** at the top right (your name): My profile, Notifications, My activity, My
    sessions, My API tokens, Calendar subscription and **Log out**;
  - the **bell** at the top: your latest notifications.
- **Language.** The back office is shown in the language of your profile (Catalan, Spanish or
  English). The labels quoted in this guide are the English ones.

## Signing in

### Logging in

Open `/login`, type your **Email address** and **Password** and press **Log in**.

- **Remember me on this device** (when offered) keeps you signed in on that browser for a
  limited number of days (14 by default). Do not use it on shared computers.
- A **Check** field (a small arithmetic operation drawn in an image) may appear if the
  administrators turned on the captcha: type the result; **Another image** draws a new one.
- After several failed attempts the login is blocked for some minutes ("Too many attempts").
  Error messages never say whether the address or the password was wrong, on purpose.
- If your account has two-factor authentication, a **Verification code** screen follows (see
  [Two-factor authentication](#two-factor-authentication)).
- If an administrator asked you to change your password, you are sent to the password form
  first ("You must change your password before you continue").
- When you sign in from a device or browser the site does not know, you get an email **Sign-in
  from a new device**. If it was not you, follow **Review my sessions** in the email, log that
  session out and change your password.

### Logging in with a link by email

If the login page shows **Log in with a link by email**, you can sign in without typing your
password: open it (`/login/magic-link`), type your email address and press **Send the link**.
You receive an email with a single-use link that expires after a short time. If your account has
two-factor authentication, the code is still asked after the link.

### Forgotten password

Press **Forgot your password?** on the login page (`/reset-password`), type your email address
and press **Send the link**. The email contains a single-use link to choose a **New password**
(type it twice and press **Save the password**). For privacy, the page always says that an email
was sent "if an account exists for this address": check your spam folder too.

### Session lock and expiry

- If the administrators set an idle time, the screen **Session locked** appears after a period
  without activity: type your password and press **Unlock** (or **Not you? Log out**).
- A session also ends a fixed time after the login (12 hours by default), even while you use
  it: just log in again.
- A message "This session was ended from another device" means that you (or an administrator)
  closed this session from another place, or that the password was changed.

### Logging out

Open the user menu and choose **Log out**. Opening `/logout` asks "Do you want to log out on
this device?" before closing the session.

## My profile

User menu › **My profile** (`/backend/profile`).

The **Personal details** card holds:

| Field | What it does |
|---|---|
| **Avatar** | **Upload a file**: JPG, PNG or WebP, up to 2 MB. The image is re-encoded without its metadata and only signed-in users see it. Save the profile to apply the change. |
| **Name** | The name the others see. |
| **Email address** | Your login address. Changing it requires your **Current password**. |
| **Language** | Catalan, Spanish or English: the language of the back office and of your emails. |
| **Time zone** | Dates and times are shown in this time zone. |
| **Colour mode** | Automatic (system), Light or Dark. The colour-mode button of the top bar also changes it. |
| **Date of birth** and **Share my birthday in the calendar** | Optional. Only the day and month are shared, and only with the groups the administrators allow. |
| **Current password** | Only needed when you change the email address. |

Press **Save** to keep the changes. The **Account** card on the right shows your groups and the
buttons **Change the password**, **Two-factor authentication** and **My sessions**.

### Changing the password

Profile › **Change the password** (`/backend/profile/password`): type the current password, the
**New password** and **Repeat the new password**. The minimum length is shown on the form (8
characters by default; a long phrase is safer and easier to remember). Common or leaked passwords
and passwords based on your email are refused. Changing the password **closes your other open
sessions and revokes the tokens of your apps**.

## Two-factor authentication

Profile › **Two-factor authentication** (`/backend/profile/two-factor`). With it, logging in
needs your password **and** a 6-digit code. Some groups require it: then the page says "Your
group requires two-factor authentication" and you must set it up to continue; you can change the
method but not turn it off.

Changes on this page ask you to [confirm your identity](#confirming-your-identity) (password, and
the current code if you already use 2FA).

### Choosing a method

The **Method** card offers two methods; press **Use this method** on the one you want:

- **Authenticator app** (recommended): Google Authenticator, Microsoft Authenticator, Aegis,
  1Password… The page **Set up an authenticator app** shows the steps:
  1. install an authenticator app on your phone;
  2. scan the QR code, or type the key by hand ("Key (if you cannot scan the code)");
  3. type the 6-digit code the app shows and press **Activate**.
- **Code by email**: a 6-digit code is emailed to you at every login (it expires in 10
  minutes). On the verification screen, **Send a new code** sends another one.

"Not available: the server has no encryption key" means the administrators must configure the
server before the app method can be used.

### Recovery codes

When you turn on two-factor authentication you get **10 recovery codes**. They are shown **only
once**: copy them and keep them in a safe place (a password manager, or printed at home). Each
code lets you log in once if you lose access to your method: on the verification screen choose
**Use a recovery code**. The **Recovery codes** card shows how many unused codes you have left;
**Generate new codes** gives you a new set and invalidates the old one.

### If you lose your phone

1. Log in with your password and, on the **Verification code** screen, choose **Use a recovery
   code**.
2. Go to Profile › Two-factor authentication and set up the authenticator app again on the new
   phone (or switch to **Code by email**), then **Generate new codes**.
3. If you have no recovery codes left, ask an administrator: in Users they can use **Reset 2FA**
   on your account. You get a notification, and if your group requires 2FA you will set it up
   again at your next login.

If someone else may have your phone or your codes, also change your password and log out your
other sessions.

### Turning it off

**Turn off two-factor authentication** (only when your group does not require it) leaves your
account protected by the password alone.

## My sessions

User menu › **My sessions** (`/backend/profile/sessions`).

- **Open sessions** lists the devices where you are logged in: **Device**, **IP address**,
  **Started** and **Last activity**. Your current one is marked **This session**; **Remembered**
  marks sessions opened with "Remember me".
- **Log out** on a row closes that session at once; **Log out all other sessions** closes every
  session except the current one. To close the current one, use **Log out** in the user menu.
- The **API tokens** card lists the tokens of your apps (**App**) and your personal tokens
  (**Personal**) with their last use; **Revoke** stops a token at once.

If you do not recognise a session, log it out and change your password.

## Confirming your identity

Sensitive actions (for example changing the two-factor settings, creating a personal API token
or a calendar feed address) show the screen **Confirm your identity**: type your **Password**
and, if you use two-factor authentication, the current code (or a recovery code), and press
**Confirm**. With the email method, **Email me a code** sends the code. Your identity stays
confirmed for a few minutes (15 by default), so related actions do not ask again; if the action
did not finish, repeat it. Too many failed attempts end the session.

## Dashboard

Sidebar › **Dashboard** (`/backend`), the first screen after logging in.

- It shows **widgets** (cards such as Upcoming events, Notices, Latest news, Quick links, My
  pending to-do tasks…). Administrators decide which widgets each group has (Settings ›
  **Dashboard widgets**); you only see a widget when your group has it **and** you hold the
  permission it needs.
- **Customise** lets you arrange your own dashboard: drag the widgets by their handle (or use
  the arrow keys on it) to reorder them, change their size (Small, Medium, Large, Full width) or
  hide them. Changes are saved at once; press **Done** to finish. Hidden widgets are listed under
  **Hidden widgets**, from where you can show them again.
- **Restore the group layout** forgets your own order, sizes and hidden widgets.
- Without JavaScript, `/backend/dashboard/customize` offers the same options in a table
  (**Position**, **Size**, **Visible**, then **Save**).

If a widget fails, only that card shows an error; the rest of the dashboard keeps working.

## Notifications

- **The bell** in the top bar shows the number of unread notifications and opens the latest
  ones. Clicking one opens it and marks it as read. The bell also offers **Mark all as read**,
  **See all** and **Preferences**.
- **The list:** user menu › **Notifications** (`/backend/notifications`) shows every
  notification with its date and whether it is **Read** or **Unread**. **Mark as read** works on
  one notification or on the selected ones, and **Mark all as read** on all of them.
- **Preferences:** **Notification preferences** (from the list or the bell;
  `/backend/profile/notifications`) lets you choose, for each kind of notification, whether you
  receive it **In the application**, **By email**, both or neither. Notifications marked
  **Mandatory** cannot be turned off.

Examples of notifications: a new notice in the news, an event reminder you asked for, a
two-factor reset by an administrator, a failed scheduled task (for administrators).

## Files

Sidebar › **Files** (`/backend/files`), for users with access to the file manager. Other users
only upload files through the fields of the forms (see [Files in forms](#files-in-forms-the-file-picker)).

### Spaces

The **Folders** tree on the left shows the spaces you may open:

| Space | Contents |
|---|---|
| **My files** | Your personal space (private). Only you see it. It is offered to the groups chosen by the administrators (by default `admin` and `editor`). |
| **Shared (public)** | Shared folders whose files have a public address (anyone with the address can open them: use it for images and documents of the website). |
| **Shared (private)** | Shared folders whose files are served only to the people the folder permissions allow. |

### Working with files

- **Upload** (or drag the files onto the list: "Drop the files here to upload them") uploads to
  the **Current folder**. The **Uploads** panel shows the progress of each file. Allowed types
  and the maximum size are set by the administrators; files that do not comply are refused with
  a message.
- Search by name, alternative text or tag; filter by **Type**, dates (**From**, **To**) and
  **Tag**; **Sort by** Name, Newest or Largest; switch the **View** between **List** and
  **Grid**.
- Clicking a file opens **File details**: preview, size, dimensions, **Download**, **Preview**,
  **Copy public URL** (public files) or **Copy URL** (private files), **Move** to another folder,
  and the fields **Name**, **Tags** (comma separated) and **Alternative text** (describe images for
  people who cannot see them).
- **Move to trash** deletes a file. The **Trash** view lists deleted files: **Restore** brings one
  back and **Delete for good** removes it permanently (it cannot be undone).

### Folders and permissions

Depending on your permissions you can create (**New folder**), **Rename** and **Delete folder**
(only empty folders: no files, no files in the trash and no subfolders). The **Permissions**
button of a shared folder gives rights (**View**, **Upload**, **Edit**, **Delete**, **Manage**) to
a **Group**, a **User** or **Every signed-in user**; subfolders inherit them unless they define
their own.

### Quotas

A space or folder can have a size limit. The file manager shows "*X* of *Y* used"; files in the
trash count until they are deleted for good. When an upload would exceed it, it is refused ("The
folder quota would be exceeded"): empty the trash or delete files. The personal space has 100 MB
by default.

### Files in forms (the file picker)

Fields that take a file (an avatar, a cover image, attachments…) show **Upload a file** and, when
the field allows it, **Choose a file**, which opens a dialog with the folders you may use for that
field (**Parent folder** to go up, **Choose** to confirm). The current file is shown with a button
to remove it. The field checks the type and size before uploading. The change is kept only when
you save the form.

In the rich-text editor, **Insert an image** opens the same dialog (**Upload an image** or choose
one), asks for the **Alternative text (describe the image)** and inserts it. Leave the alternative
text empty only if the image is decorative.

## Pages

Sidebar › **Pages** (`/backend/pages`): the pages of the public website.

- The form has one tab per language (Catalan is required): **Title**, **Slug** (part of the
  address; made from the title when empty), **Summary**, **Text**, the search-engine fields
  (**Meta title**, **Meta description**) and the featured image. Untranslated texts fall back to
  Catalan.
- **Parent page** places the page in a section: its address starts with the parent's.
- **Visibility**: **Public**, **Signed-in users**, or **Groups** (only the members of the chosen
  groups).
- **Status**: **Draft**, **Scheduled** or **Published**. **Publish** publishes the page (with a
  future **Publication date** it is scheduled); **Unpublish** returns it to draft.
- **Preview** shows the page as it will look, even as a draft. **Revisions** lists the saved
  versions with their changes, and **Restore** brings one back (a new revision is kept).
  **Duplicate** makes a draft copy.

Publishing usually needs a specific permission; an editor who cannot publish saves drafts.

## Menus

Sidebar › **Menus** (`/backend/menus/public`): the menus of the public website.

- Each menu has a **Location** (where the theme shows it). **Items** opens its items.
- **Add an item** (or **Add an item inside** an existing one) links a page, an address, a route
  or a link offered by the modules (a news category, the calendar…), with its visibility (for
  example only signed-in users) and whether it opens in a new tab.
- Reorder and nest the items by dragging the handle, with the arrow keys on it, or with the
  buttons, and then save the order. Items whose target no longer exists are marked **Target not
  available**.

Administrators can also customise the back-office menu (Settings › **Back-office menu**); entries
keep their permission wherever they are placed.

## News

Sidebar › **News**:

- **News** (`/backend/news`): the reader. It shows the articles and notices you may read; filter
  by **Type**, **Category** and **Only unread**, then **Filter**. Unread items are marked **New**,
  pinned ones first. Open an item to read it and press **Mark as read**.
- **All posts** (`/backend/news/posts`) and **My posts** (`/backend/news/mine`): writing and
  managing posts (the second one lists only yours). A post is an **Article** or a **Notice**
  (notices have a priority and can be pinned; private notices notify their readers when they are
  published). **Visibility**: **Public**, **Private** (signed-in users) or **Groups**. The status
  goes **Draft** → **Scheduled** / **Published** → **Expired** (after the **Expiry date**).
  **Publish** and **Unpublish** change it; **Readers** shows who has read a post (with the
  permission).
- **Categories** (`/backend/news/categories`): the news categories.

A notice with an **Event date** also appears in the calendar, for the same audience.

## Calendar and events

### The calendar

Sidebar › **Calendar** (`/backend/calendar`) shows every event you may see.

- Views: month, week, day and list (on a phone the list is shown first). Use the arrows and
  "today" to move.
- **Calendars**: besides **Events**, modules can add their own calendars (for example the
  notices of the news, birthdays, project deadlines). Tick the ones you want and press **Apply**;
  the eye button shows **Only** that calendar, **Show all** shows them all again.
- **Category** filters the events by category (**All categories** to clear it).
- Click an event to see its details (**When**, **Repeats**, **Location**, **Category**,
  **Organiser**, **Link**). From there:
  - **Remind me** + **Save** sends you a notification some time before it starts (**No reminder**
    removes it);
  - **Add to my calendar (.ics)** downloads the event for another calendar application;
  - **Edit** (with the permission), or **Edit in …** for events managed by another module.

The public calendar of the website is at `/calendar` (public events only).

### Managing events

Calendar › **Events** (`/backend/events`) and **Categories** (`/backend/events/categories`), with
the permissions. An event has a **Title**, **Start**, **End** (optional), **All day**,
**Location**, **Category**, **Status** (**Confirmed**, **Tentative**, **Cancelled**) and
**Visibility**: **Public** (also on the website), **Private** (any signed-in user) or
**Restricted** (only the chosen **Groups** and **Users**). **Repeat** (Daily, Weekly, Monthly,
Yearly) with **Every**, **On the days**, **Until** or **Number of times** and **Skipped dates**
makes recurring events. **Duplicate** copies an event.

### Subscribing from another calendar application

User menu › **Calendar subscription** (`/backend/calendar/subscription`) gives you a private
address (an iCal feed) that calendar applications can **subscribe** to. The feed shows every event
you can see here and updates itself; it is read-only (changes are made in the back office).

1. Press **Create my feed address** and [confirm your identity](#confirming-your-identity).
2. The page shows **Your calendar feed address** (it looks like
   `https://example.org/ical/user/<secret>.ics`). **Copy** it now: it is shown only once, because
   it is stored encrypted. Anyone with this address can read your calendar, so do not share it.
3. Add it to your application as a calendar **subscribed by URL** (not as an imported file):

| Application | Steps |
|---|---|
| **Google Calendar** | On a computer: Other calendars › + › From URL, paste the address and choose "Add calendar". It then appears on the phone too. |
| **Outlook** | Add calendar › Subscribe from web, paste the address, give it a name and import it. |
| **Apple Calendar (macOS, iPhone)** | Use **Open in my calendar app**, or File › New Calendar Subscription (Mac) / Settings › Calendar › Accounts › Add subscribed calendar (iPhone). |
| **Synology Calendar** | Calendar › Create › Subscribe to a calendar, paste the address and choose how often to update it. |
| **Thunderbird** | New calendar › On the network › iCalendar (ICS), paste the address. |

Applications decide how often they read the feed (usually between 15 minutes and a day), so a
change may take that long to appear.

- **What the feed includes:** tick some **Calendars** or **Categories** and press **Save** to
  limit the feed; leave everything unchecked to include all your events.
- **Regenerate the address** (resetting the feed) creates a new address and makes the old one
  stop working at once, in every application that uses it: do it if you lost the address or
  think someone else has it, then update your applications. **Revoke** stops the feed without a
  new address.
- The card shows the **State**, when it was **Created** and when it was **Last read by a
  client**.
- When the site publishes its public calendar, the page also shows the address of the **public
  events** feed (`/ical/public/all.ics`), which needs no login and can be shared.

## To-do tasks

Sidebar › **To-do tasks** (`/backend/tasks`), when the module is enabled.

- The **board** shows the to-do tasks in the columns **To do**, **In progress** and **Done**. Drag
  a card to another column, or choose its status in **Move to** and press **Move**. Depending on
  your permissions you see every to-do task or only the ones assigned to you. **New to-do task**
  creates one; filter by project.
- **All to-do tasks** (`/backend/tasks/tasks`) is the full list: **Project**, **Priority**,
  **Labels**, **Due date**, **Assignees**, attachments and **Complete** to mark one as done.
- **Projects** and **Requests** (requests sent from the public form; **Accept** creates a to-do
  task, **Reject** closes the request), with the permissions.
- The dashboard widget **My pending to-do tasks** lists yours.

## My activity

User menu › **My activity** (`/backend/activity/mine`) lists what you did and the security events
of your account: logins and failed logins, password and email changes, two-factor changes,
sessions closed, tokens created or revoked, records created, edited or deleted… Each entry has
its **Date**, type (**Activity** or **Security**), **Action** and **Summary**; open one to see the
changes. The log cannot be edited. If you see something you did not do, change your password,
log out your other sessions and tell an administrator.

## My API tokens

User menu › **My API tokens** (`/backend/profile/api-tokens`), for users allowed to create them
(by default the `editor` and `admin` groups). A personal token lets a program or an integration
use the API on your behalf; it can never do more than you can.

1. Under **New token**, give it a **Name** (where you will use it), choose **Expires in** (7, 30,
   90 or 365 days) and **What it can do**: **Everything I can do** or **Only these permissions**.
2. Press **Create token** and [confirm your identity](#confirming-your-identity).
3. **Your new token** is shown only once: copy it now.

**My tokens** lists your tokens with their last use; **Revoke** stops one at once (programs that
use it stop working). Permissions you lose later are also lost by the token. Tokens are also
listed, and can be revoked, in [My sessions](#my-sessions).

## Quick reference

| Screen | Where | Address |
|---|---|---|
| Log in | — | `/login` |
| Dashboard | Sidebar | `/backend` |
| My profile | User menu | `/backend/profile` |
| Change the password | My profile | `/backend/profile/password` |
| Two-factor authentication | My profile | `/backend/profile/two-factor` |
| My sessions | User menu | `/backend/profile/sessions` |
| Notifications | User menu, bell | `/backend/notifications` |
| Notification preferences | Notifications, bell | `/backend/profile/notifications` |
| My activity | User menu | `/backend/activity/mine` |
| My API tokens | User menu | `/backend/profile/api-tokens` |
| Calendar subscription | User menu | `/backend/calendar/subscription` |
| Files | Sidebar | `/backend/files` |
| Pages | Sidebar | `/backend/pages` |
| Menus | Sidebar | `/backend/menus/public` |
| News | Sidebar | `/backend/news` |
| Calendar | Sidebar | `/backend/calendar` |
| To-do tasks | Sidebar | `/backend/tasks` |
