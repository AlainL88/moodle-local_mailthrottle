# Outgoing Mail Throttle (`local_mailthrottle`)

A lightweight Moodle 5.3+ local plugin that prevents SMTP rate-limit and anti-spam blocks by spacing outgoing emails using Moodle's native `\core\hook\email\before_email_to_user` hook (`MDL-69724`).

## Why `local_mailthrottle`?

Moodle does not include a built-in rate limiter for outgoing emails. During bulk operations—such as mass user imports (`send_new_user_passwords_task`) or large forum digests/notifications—Moodle's cron dispatches hundreds or thousands of emails as fast as the SMTP server accepts them. On shared hosting, institutional mail servers, or rate-limited SMTP providers, this frequently triggers anti-spam blocks or account suspensions.

`local_mailthrottle` solves this cleanly without modifying Moodle core files.

## How It Works

1. **Official Moodle 5.3+ Hook (`MDL-69724`)**: Registers a high-priority callback on `\core\hook\email\before_email_to_user` in `db/hooks.php`.
2. **Atomic File-Lock Slot Reservation**: Coordinates concurrent CLI cron workers using an exclusive file lock (`$CFG->tempdir/local_mailthrottle.lock`) with zero database writes.
3. **Non-Blocking Lock**: Each worker reads the last scheduled send timestamp, reserves the next available slot (`lastslot + delay`), writes it back, **releases the lock immediately**, and only then calls `usleep()`. Parallel workers do not block each other while waiting for their own send slot.
4. **Smart Bypass**: Automatically skips throttling when `$CFG->noemailever` is enabled, the email is already blocked by another hook listener, or the recipient is invalid (deleted, suspended, `nologin`, or `.invalid` address).

## Features

- **Zero core hacks**: Pure `local` plugin using Moodle's official Hook API.
- **Zero database overhead**: Uses `$CFG->tempdir/local_mailthrottle.lock` instead of DB locks/tables.
- **CLI-only mode (default)**: Throttles cron/CLI bulk tasks while keeping interactive web requests (e.g., password resets, email confirmations) instantaneous.
- **Configurable delay**: Set the interval in seconds under *Site administration → Server → Outgoing Mail Throttle*.

## Requirements

- Moodle **5.3** or later (`2026100500`).
- PHP **8.3** or later.

## Installation

### From ZIP
1. Download the plugin ZIP archive.
2. Extract it so the folder is named `mailthrottle` and place it in `/path/to/moodle/public/local/mailthrottle` (or `/path/to/moodle/local/mailthrottle` depending on your Moodle root layout).
3. Visit *Site administration → Notifications* or run `php admin/cli/upgrade.php`.

### From Git
```bash
git clone https://github.com/AlainL88/moodle-local_mailthrottle.git /path/to/moodle/public/local/mailthrottle
php admin/cli/upgrade.php
```

## Configuration

Navigate to *Site administration → Server → Outgoing Mail Throttle*:

| Setting | Default | Description |
| :--- | :--- | :--- |
| **Enable mail throttling** (`enabled`) | `1` (Enabled) | Master switch to enable or disable outgoing email throttling. |
| **Delay between emails (seconds)** (`delayseconds`) | `12` | Minimum interval in seconds between consecutive individual emails (`0` disables delay). |
| **Throttle CLI / cron only** (`clionly`) | `1` (Enabled) | Recommended. Only applies throttling to `CLI_SCRIPT` / cron tasks. |

### Delay Examples

| `delayseconds` | Max Throughput | Typical Use Case |
| :--- | :--- | :--- |
| `0` | Unlimited | Throttling disabled |
| `12` | ~300 emails/hour (5/min) | Default; moderate shared SMTP limits |
| `15` | ~240 emails/hour (4/min) | Conservative shared hosting limits |
| `36` | ~100 emails/hour | Strict hourly SMTP quotas (e.g., 100/hr) |

### Interaction with `$CFG->smtpmaxbulk`

`delayseconds` spaces **individual emails**, not SMTP connections. If you configure a high delay (e.g., `> 20–30` seconds), an open SMTP connection may time out while waiting between messages. In that case, set **SMTP session limit** (`$CFG->smtpmaxbulk`) to `1` under *Site administration → Server → Email → Outgoing mail configuration* so Moodle opens a fresh SMTP connection per message.

## Cron Behavior & Reliability

- **Long-running bulk tasks**: While a large batch of emails is being dispatched, the active cron task remains occupied until the batch finishes. Subsequent cron runs will skip the locked task until it completes, and other scheduled tasks sharing the same cron runner may be delayed.
- **Interruption / restart safety**: If a cron worker is interrupted or restarted mid-batch, Moodle's task/notification queue resumes from where it left off without duplicate sends. The lock file simply stores the last reserved timestamp in `$CFG->tempdir`.

## Limitations & When to Use an External SMTP Relay Instead

- **Not a queue**: `local_mailthrottle` paces sends synchronously inside the sending PHP process (`usleep()`). For sites sending tens of thousands of emails per day, sleeping inside cron workers will saturate PHP CLI workers and delay scheduled tasks.
- **When to use an external relay**: If you regularly send high-volume mailings and need fast cron execution, use a dedicated transactional SMTP relay (e.g., Amazon SES, Postmark, Mailgun, SendGrid, or a local Postfix relay with rate-limiting).

## Privacy

This plugin does not store any personal data and implements `\core_privacy\local\metadata\null_provider`.

## License

Licensed under the [GNU General Public License v3.0](LICENSE).
