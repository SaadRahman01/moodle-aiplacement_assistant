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

namespace aiplacement_assistant\external;

use aiplacement_assistant\ai_bridge;
use aiplacement_assistant\fake_ai_bridge;
use aiplacement_assistant\intent\registry;

/**
 * Tests for the web services.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(send_message::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_history::class)]
final class send_message_test extends \advanced_testcase {
    /**
     * Load fixtures.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/ai/placement/assistant/tests/fixtures/fake_ai_bridge.php');
        require_once($CFG->dirroot . '/ai/placement/assistant/tests/fixtures/echo_intent.php');
    }

    /**
     * Send a message and read history.
     */
    public function test_send_and_history(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $fake = new fake_ai_bridge();
        $fake->responses = ['{"intent":"general_help","params":{}}', 'Two things found.'];
        \core\di::set(ai_bridge::class, $fake);
        \core\di::set(registry::class, new registry([\aiplacement_assistant\echo_intent::class]));
        $ctx = \context_system::instance()->id;
        $out = \core_external\external_api::clean_returnvalue(
            send_message::execute_returns(),
            send_message::execute($ctx, 'what is due')
        );
        $this->assertTrue($out['success']);
        $this->assertSame('Two things found.', $out['text']);
        $this->assertSame('SECRETNAME', $out['rows'][0]['value']);
        $this->assertSame('Link', $out['links'][0]['label']);
        $this->assertSame([], $out['menu']);

        $h = \core_external\external_api::clean_returnvalue(
            get_history::execute_returns(),
            get_history::execute($ctx)
        );
        $this->assertCount(2, $h['messages']);
        $this->assertSame('user', $h['messages'][0]['role']);
        $this->assertSame('what is due', $h['messages'][0]['text']);
    }

    /**
     * Guests cannot call.
     */
    public function test_guest_cannot_call(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $this->expectException(\moodle_exception::class);
        send_message::execute(\context_system::instance()->id, 'hi');
    }

    /**
     * Guests cannot read history.
     */
    public function test_guest_cannot_read_history(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $this->expectException(\moodle_exception::class);
        get_history::execute(\context_system::instance()->id);
    }
}
