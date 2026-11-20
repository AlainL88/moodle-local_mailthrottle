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

/**
 * English language strings for local_mailthrottle.
 *
 * @package    local_mailthrottle
 * @copyright  2026 Alain Lima
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Outgoing Mail Throttle';
$string['enabled'] = 'Enable mail throttling';
$string['enabled_desc'] = 'When enabled, each outgoing email is spaced by the configured delay to avoid SMTP rate-limit blocks. Note: while a large batch of emails is being sent, the running cron task remains busy until all emails are dispatched, which can delay other scheduled tasks.';
$string['delayseconds'] = 'Delay between emails (seconds)';
$string['delayseconds_desc'] = 'Minimum number of seconds between two consecutive individual emails (not per SMTP connection; e.g. 12 = max 300/hour, 36 = max 100/hour; 0 = disabled). If you use a high delay (e.g. > 30s), consider setting "SMTP session limit" (smtpmaxbulk) to 1 to avoid SMTP idle timeouts.';
$string['clionly'] = 'Throttle CLI / cron only';
$string['clionly_desc'] = 'Recommended: only throttle emails sent from CLI cron tasks so interactive web requests (e.g. password reset) are never delayed.';
$string['privacy:metadata'] = 'The Outgoing Mail Throttle plugin does not store any personal data.';
