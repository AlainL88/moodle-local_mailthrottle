# Changelog

All notable changes to `local_mailthrottle` will be documented in this file.

## [1.0.0] - 2026-11-20

### Added
- Initial stable release for Moodle 5.3+ (`MDL-69724`).
- Hook callback on `\core\hook\email\before_email_to_user` (`db/hooks.php`, `classes/hook_callbacks.php`).
- Atomic file-lock slot reservation in `$CFG->tempdir/local_mailthrottle.lock` to coordinate concurrent CLI cron workers without database writes.
- Admin settings (`settings.php`): `enabled`, `delayseconds` (default `12`), and `clionly` (default `1`).
- English (`lang/en/local_mailthrottle.php`) and Italian (`lang/it/local_mailthrottle.php`) language packs.
- Privacy API `null_provider` implementation (`classes/privacy/provider.php`).
- PHPUnit test suite (`tests/hook_callbacks_test.php`) and GitHub Actions CI workflow (`.github/workflows/ci.yml`).
