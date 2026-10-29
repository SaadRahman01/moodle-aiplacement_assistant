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
use aiplacement_assistant\intent\access_check;
use aiplacement_assistant\intent\forum_summary;
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
     * Real forum author names never enter the stored history or the next turn's router prompt.
     */
    public function test_forum_summary_names_never_reach_next_prompt(): void {
        global $USER;
        $fake = $this->setup_fakes([
            '{"intent":"forum_summary","params":{"forum":"class"}}',
            "- Person 1 asked about X\n- Person 2 answered",
            '{"intent":"general_help","params":{}}',
            'Some help.',
        ], [forum_summary::class, general_help::class]);
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->enrol_user($USER->id, $course->id, 'editingteacher');
        $zelda = $gen->create_and_enrol($course, 'student', ['firstname' => 'Zelda', 'lastname' => 'Zebra']);
        $tom = $gen->create_and_enrol($course, 'student', ['firstname' => 'Tomas', 'lastname' => 'Tiger']);
        $forum = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Class forum']);
        $forumgen = $gen->get_plugin_generator('mod_forum');
        $d = $forumgen->create_discussion(['course' => $course->id, 'forum' => $forum->id, 'userid' => $zelda->id,
            'message' => 'How does X work?']);
        $forumgen->create_post(['discussion' => $d->id, 'parent' => $d->firstpost, 'userid' => $tom->id,
            'message' => 'Like Y.']);
        $ctx = \context_course::instance($course->id);

        $a = new assistant();
        $out = $a->respond(new request($ctx, (int) $USER->id, 'Summarise the class forum'));
        $this->assertStringContainsString('Zelda Zebra asked about X', $out['text']);
        $a->respond(new request($ctx, (int) $USER->id, 'And what else?'));

        $this->assertCount(4, $fake->prompts);
        foreach (['Zelda', 'Zebra', 'Tomas', 'Tiger'] as $name) {
            $this->assertStringNotContainsString($name, $fake->prompts[2]);
            foreach ((new conversation((int) $USER->id))->history() as $m) {
                $this->assertStringNotContainsString($name, $m['text']);
            }
        }
        $this->assertStringContainsString('Person 1 asked about X', $fake->prompts[2]);
    }

    /**
     * The access check's fallback text names the checked user, but that name is never stored or re-sent.
     */
    public function test_access_check_name_never_reaches_next_prompt(): void {
        global $USER;
        $fake = $this->setup_fakes([
            '{"intent":"access_check","params":{"user":"sslack","course":"SC1"}}',
            null,
            '{"intent":"general_help","params":{}}',
            'Some help.',
        ], [access_check::class, general_help::class]);
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['shortname' => 'SC1', 'fullname' => 'Smoke course']);
        $gen->create_and_enrol($course, 'student', ['username' => 'sslack', 'firstname' => 'Sue', 'lastname' => 'Slacker']);
        $ctx = \context_system::instance();

        $a = new assistant();
        $out = $a->respond(new request($ctx, (int) $USER->id, 'Why cannot sslack get into SC1?'));
        $this->assertStringContainsString('Sue Slacker', $out['text']);
        $a->respond(new request($ctx, (int) $USER->id, 'And what else?'));

        $this->assertCount(4, $fake->prompts);
        foreach (['Sue', 'Slacker'] as $name) {
            $this->assertStringNotContainsString($name, $fake->prompts[2]);
            foreach ((new conversation((int) $USER->id))->history() as $m) {
                $this->assertStringNotContainsString($name, $m['text']);
            }
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
