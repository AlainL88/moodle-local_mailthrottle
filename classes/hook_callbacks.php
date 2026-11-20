<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_mailthrottle;

use core\hook\email\before_email_to_user;

/**
 * Hook callbacks for local_mailthrottle.
 *
 * @package    local_mailthrottle
 * @copyright  2026 Alain Lima
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Throttle outgoing emails by spacing sends according to plugin settings.
     *
     * Uses an atomic file lock in $CFG->tempdir to reserve the next send slot
     * across concurrent CLI cron workers without touching the database.
     *
     * @param before_email_to_user $hook The before_email_to_user hook instance.
     */
    public static function before_email_to_user(before_email_to_user $hook): void {
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST) || (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING)) {
            return;
        }

        $delay = self::get_effective_delay($hook->email->is_blocked(), $hook->email->user ?? null);
        if ($delay <= 0) {
            return;
        }

        $sleeptime = self::reserve_slot($delay);
        if ($sleeptime > 0.0) {
            $microseconds = (int)round($sleeptime * 1000000);
            if ($microseconds > 0) {
                usleep($microseconds);
            }
        }
    }

    /**
     * Determine the effective throttle delay in seconds for an email send.
     *
     * @param bool $isblocked Whether the email has already been blocked by a hook listener.
     * @param \stdClass|null $user Recipient user object.
     * @param bool|null $iscli Override CLI_SCRIPT detection (used in unit tests).
     * @return int Delay in seconds, or 0 if throttling should not be applied.
     */
    public static function get_effective_delay(bool $isblocked, ?\stdClass $user, ?bool $iscli = null): int {
        global $CFG;

        if ($isblocked || !empty($CFG->noemailever)) {
            return 0;
        }

        if (
            empty($user)
            || empty($user->id)
            || empty($user->email)
            || !empty($user->deleted)
            || !empty($user->suspended)
            || ($user->auth ?? '') === 'nologin'
            || str_ends_with($user->email, '.invalid')
        ) {
            return 0;
        }

        $enabled = get_config('local_mailthrottle', 'enabled');
        if ($enabled === '0' || $enabled === '') {
            return 0;
        }

        $iscli = $iscli ?? (defined('CLI_SCRIPT') && CLI_SCRIPT);
        $clionly = get_config('local_mailthrottle', 'clionly');
        if (!in_array($clionly, ['0', ''], true) && !$iscli) {
            return 0;
        }

        $delay = (int)get_config('local_mailthrottle', 'delayseconds');
        return $delay > 0 ? $delay : 0;
    }

    /**
     * Reserve the next send slot in the atomic lock file and return the wait time in seconds.
     *
     * @param int $delay Configured delay between emails in seconds.
     * @param float|null $now Optional current timestamp (microtime) for deterministic testing.
     * @return float Seconds to sleep before sending.
     */
    public static function reserve_slot(int $delay, ?float $now = null): float {
        global $CFG;

        $lockfile = $CFG->tempdir . '/local_mailthrottle.lock';
        make_temp_directory('', false);
        $fp = @fopen($lockfile, 'c+');
        if (!$fp) {
            debugging('local_mailthrottle: unable to open lock file ' . $lockfile, DEBUG_DEVELOPER);
            return (float)$delay;
        }

        $sleeptime = 0.0;
        $locked = flock($fp, LOCK_EX);
        if ($locked) {
            rewind($fp);
            $raw = trim((string)stream_get_contents($fp));
            $lastslot = is_numeric($raw) ? (float)$raw : 0.0;
            $now = $now ?? microtime(true);
            $nextslot = max($now, $lastslot + (float)$delay);
            $sleeptime = $nextslot - $now;

            ftruncate($fp, 0);
            rewind($fp);
            if (fwrite($fp, sprintf('%.6F', $nextslot)) === false) {
                debugging('local_mailthrottle: unable to write slot to ' . $lockfile, DEBUG_DEVELOPER);
            }
            fflush($fp);
            flock($fp, LOCK_UN);
        } else {
            debugging('local_mailthrottle: unable to acquire lock on ' . $lockfile, DEBUG_DEVELOPER);
        }
        fclose($fp);

        if (!$locked) {
            return (float)$delay;
        }

        return $sleeptime;
    }
}
