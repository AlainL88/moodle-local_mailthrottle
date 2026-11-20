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

/**
 * Unit tests for {@see hook_callbacks}.
 *
 * @package    local_mailthrottle
 * @category   test
 * @copyright  2026 Alain Lima
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->remove_lock_file();
    }

    /**
     * Clean up lock file after each test.
     */
    protected function tearDown(): void {
        $this->remove_lock_file();
        parent::tearDown();
    }

    /**
     * Remove the throttle lock file if present.
     */
    private function remove_lock_file(): void {
        global $CFG;

        $lockfile = $CFG->tempdir . '/local_mailthrottle.lock';
        if (file_exists($lockfile)) {
            @unlink($lockfile);
        }
    }

    /**
     * Build a minimal valid recipient user object.
     *
     * @return \stdClass
     */
    private function create_valid_recipient(): \stdClass {
        return (object)[
            'id' => 42,
            'email' => 'recipient@example.com',
            'deleted' => 0,
            'suspended' => 0,
            'auth' => 'manual',
        ];
    }

    /**
     * Test that get_effective_delay respects enabled, clionly, and delayseconds settings.
     */
    public function test_get_effective_delay_configuration(): void {
        $user = $this->create_valid_recipient();

        // Default plugin config: enabled=1, delayseconds=12, clionly=1.
        set_config('enabled', '1', 'local_mailthrottle');
        set_config('delayseconds', '12', 'local_mailthrottle');
        set_config('clionly', '1', 'local_mailthrottle');

        $this->assertSame(12, hook_callbacks::get_effective_delay(false, $user, true));
        $this->assertSame(0, hook_callbacks::get_effective_delay(false, $user, false));

        // Allow web requests when clionly=0.
        set_config('clionly', '0', 'local_mailthrottle');
        $this->assertSame(12, hook_callbacks::get_effective_delay(false, $user, false));
        $this->assertSame(12, hook_callbacks::get_effective_delay(false, $user, true));

        // Disabled plugin returns 0.
        set_config('enabled', '0', 'local_mailthrottle');
        $this->assertSame(0, hook_callbacks::get_effective_delay(false, $user, true));

        set_config('enabled', '', 'local_mailthrottle');
        $this->assertSame(0, hook_callbacks::get_effective_delay(false, $user, true));

        // Zero or negative delay returns 0.
        set_config('enabled', '1', 'local_mailthrottle');
        set_config('delayseconds', '0', 'local_mailthrottle');
        $this->assertSame(0, hook_callbacks::get_effective_delay(false, $user, true));

        set_config('delayseconds', '-5', 'local_mailthrottle');
        $this->assertSame(0, hook_callbacks::get_effective_delay(false, $user, true));
    }

    /**
     * Test that blocked emails and $CFG->noemailever bypass throttling.
     */
    public function test_get_effective_delay_blocked_and_noemailever(): void {
        global $CFG;

        set_config('enabled', '1', 'local_mailthrottle');
        set_config('delayseconds', '15', 'local_mailthrottle');
        set_config('clionly', '1', 'local_mailthrottle');

        $user = $this->create_valid_recipient();

        $this->assertSame(0, hook_callbacks::get_effective_delay(true, $user, true));

        $CFG->noemailever = true;
        $this->assertSame(0, hook_callbacks::get_effective_delay(false, $user, true));
    }

    /**
     * Test that invalid or non-deliverable recipient users bypass throttling.
     */
    public function test_get_effective_delay_invalid_users(): void {
        set_config('enabled', '1', 'local_mailthrottle');
        set_config('delayseconds', '15', 'local_mailthrottle');
        set_config('clionly', '1', 'local_mailthrottle');

        $this->assertSame(0, hook_callbacks::get_effective_delay(false, null, true));

        $noid = $this->create_valid_recipient();
        $noid->id = 0;
        $this->assertSame(0, hook_callbacks::get_effective_delay(false, $noid, true));

        $noemail = $this->create_valid_recipient();
        $noemail->email = '';
        $this->assertSame(0, hook_callbacks::get_effective_delay(false, $noemail, true));

        $deleted = $this->create_valid_recipient();
        $deleted->deleted = 1;
        $this->assertSame(0, hook_callbacks::get_effective_delay(false, $deleted, true));

        $suspended = $this->create_valid_recipient();
        $suspended->suspended = 1;
        $this->assertSame(0, hook_callbacks::get_effective_delay(false, $suspended, true));

        $nologin = $this->create_valid_recipient();
        $nologin->auth = 'nologin';
        $this->assertSame(0, hook_callbacks::get_effective_delay(false, $nologin, true));

        $invaliddomain = $this->create_valid_recipient();
        $invaliddomain->email = 'user@example.com.invalid';
        $this->assertSame(0, hook_callbacks::get_effective_delay(false, $invaliddomain, true));
    }

    /**
     * Test atomic lockfile slot reservation and spacing calculation.
     */
    public function test_reserve_slot_spacing(): void {
        global $CFG;

        $delay = 12;
        $lockfile = $CFG->tempdir . '/local_mailthrottle.lock';

        // First email at t=1000.000000: no wait, slot reserved at 1000.000000.
        $sleep1 = hook_callbacks::reserve_slot($delay, 1000.0);
        $this->assertEqualsWithDelta(0.0, $sleep1, 0.0001);
        $this->assertFileExists($lockfile);
        $this->assertSame('1000.000000', trim((string)file_get_contents($lockfile)));

        // Second email at t=1002.000000: waits 10.0s, slot reserved at 1012.000000.
        $sleep2 = hook_callbacks::reserve_slot($delay, 1002.0);
        $this->assertEqualsWithDelta(10.0, $sleep2, 0.0001);
        $this->assertSame('1012.000000', trim((string)file_get_contents($lockfile)));

        // Third email at t=1005.000000 (concurrent worker): waits 19.0s, slot reserved at 1024.000000.
        $sleep3 = hook_callbacks::reserve_slot($delay, 1005.0);
        $this->assertEqualsWithDelta(19.0, $sleep3, 0.0001);
        $this->assertSame('1024.000000', trim((string)file_get_contents($lockfile)));

        // Fourth email well after delay elapsed (t=1050.000000): no wait, slot resets to 1050.000000.
        $sleep4 = hook_callbacks::reserve_slot($delay, 1050.0);
        $this->assertEqualsWithDelta(0.0, $sleep4, 0.0001);
        $this->assertSame('1050.000000', trim((string)file_get_contents($lockfile)));
    }

    /**
     * Test that email_to_user executes without throttling overhead during PHPUnit runs.
     */
    public function test_email_to_user_skips_throttle_in_phpunit(): void {
        set_config('enabled', '1', 'local_mailthrottle');
        set_config('delayseconds', '60', 'local_mailthrottle');
        set_config('clionly', '1', 'local_mailthrottle');

        $user = $this->getDataGenerator()->create_user();
        $supportuser = \core_user::get_support_user();

        $sink = $this->redirectEmails();
        $result = email_to_user($user, $supportuser, 'Test subject', 'Test body');
        $emails = $sink->get_messages();
        $sink->close();

        $this->assertTrue($result);
        $this->assertCount(1, $emails);
    }
}
