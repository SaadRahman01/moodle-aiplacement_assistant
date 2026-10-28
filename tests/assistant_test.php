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

namespace aiplacement_assistant;

use aiplacement_assistant\intent\registry;
use aiplacement_assistant\intent\general_help;

/**
 * Tests for the assistant orchestrator.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(assistant::class)]
final class assistant_test extends \advanced_testcase {
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
     * Set up a fake bridge and an echo registry.
     *
     * @param array $responses Queue.
     * @param array $classes Intent classes.
     * @return fake_ai_bridge
     */
    private function setup_fakes(array $responses = [], array $classes = [echo_intent::class]): fake_ai_bridge {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $fake = new fake_ai_bridge();
        $fake->responses = $responses;
        \core\di::set(ai_bridge::class, $fake);
        \core\di::set(registry::class, new registry($classes));
        return $fake;
    }

    /**
     * Build a request.
     *
     * @param string $message Message.
     * @return request
     */
    private function req(string $message = 'hello'): request {
        global $USER;
        return new request(\context_system::instance(), (int) $USER->id, $message);
    }

    /**
     * Count stored/session messages.
     *
     * @return int
     */
    private function stored(): int {
        global $USER;
        return count((new conversation((int) $USER->id))->history());
    }

    /**
     * Not available.
     */
    public function test_not_available_returns_error(): void {
        $fake = $this->setup_fakes();
        $fake->available = false;
        $out = (new assistant())->respond($this->req());
        $this->assertFalse($out['success']);
        $this->assertSame('notavailable', $out['error']);
    }

    /**
     * Policy not accepted.
     */
    public function test_policy_not_accepted(): void {
        $fake = $this->setup_fakes();
        $fake->accepted = false;
        $out = (new assistant())->respond($this->req());
        $this->assertSame('policynotaccepted', $out['error']);
    }

    /**
     * Too long.
     */
    public function test_message_too_long(): void {
        $this->setup_fakes();
        $out = (new assistant())->respond($this->req(str_repeat('a', 2001)));
        $this->assertSame('messagetoolong', $out['error']);
    }

    /**
     * Empty message without picked intent.
     */
    public function test_empty_message_errors(): void {
        $this->setup_fakes();
        $out = (new assistant())->respond($this->req("  \n "));
        $this->assertSame('messageempty', $out['error']);
    }

    /**
     * Garbage routing gives the menu.
     */
    public function test_router_garbage_returns_menu(): void {
        $this->setup_fakes(['not json at all'], [general_help::class]);
        $out = (new assistant())->respond($this->req());
        $this->assertTrue($out['success']);
        $this->assertSame(get_string('fallbackmenu', 'aiplacement_assistant'), $out['text']);
        $this->assertSame(
            [['intent' => 'general_help', 'label' => get_string('intent_general_help', 'aiplacement_assistant')]],
            $out['menu']
        );
    }

    /**
     * Picked intent skips the router.
     */
    public function test_picked_intent_skips_router(): void {
        $fake = $this->setup_fakes(['Nice answer.']);
        $out = (new assistant())->respond($this->req(''), 'general_help');
        $this->assertTrue($out['success']);
        $this->assertSame('Nice answer.', $out['text']);
        $this->assertCount(1, $fake->prompts);
    }

    /**
     * Picked intent stores the menu label as the user message.
     */
    public function test_picked_intent_stores_label(): void {
        global $USER;
        $this->setup_fakes(['Nice answer.']);
        (new assistant())->respond($this->req(''), 'general_help');
        $h = (new conversation((int) $USER->id))->history();
        $this->assertSame(get_string('intent_general_help', 'aiplacement_assistant'), $h[0]['text']);
        $this->assertSame('Nice answer.', $h[1]['text']);
    }

    /**
     * Phrasing failure uses fallback.
     */
    public function test_phrasing_failure_uses_fallback(): void {
        $this->setup_fakes(['{"intent":"general_help","params":{}}']);
        $out = (new assistant())->respond($this->req());
        $this->assertTrue($out['success']);
        $this->assertSame('Two things.', $out['text']);
        $this->assertSame(2, $this->stored());
    }

    /**
     * AI failure on routing stores nothing.
     */
    public function test_ai_failure_on_route_stores_nothing(): void {
        $this->setup_fakes([]);
        $out = (new assistant())->respond($this->req());
        $this->assertFalse($out['success']);
        $this->assertSame('aifailed', $out['error']);
        $this->assertSame(0, $this->stored());
    }

    /**
     * Rows never reach any prompt, and are returned.
     */
    public function test_rows_never_in_any_prompt(): void {
        $fake = $this->setup_fakes(['{"intent":"general_help","params":{}}', 'Two things found.']);
        $out = (new assistant())->respond($this->req());
        $this->assertCount(2, $fake->prompts);
        foreach ($fake->prompts as $p) {
            $this->assertStringNotContainsString('SECRETNAME', $p);
        }
        $this->assertStringContainsString('"count":2', $fake->prompts[1]);
        $this->assertSame('SECRETNAME', $out['rows'][0]['value']);
        $this->assertSame('https://example.com/x', $out['links'][0]['url']);
        foreach ((new conversation((int) $this->req()->userid))->history() as $m) {
            $this->assertStringNotContainsString('SECRETNAME', $m['text']);
        }
    }

    /**
     * Rate limited.
     */
    public function test_rate_limited(): void {
        $this->setup_fakes();
        set_config('ratelimit', 1, 'aiplacement_assistant');
        $a = new assistant();
        $a->respond($this->req());
        $out = $a->respond($this->req());
        $this->assertSame('ratelimited', $out['error']);
    }
}
