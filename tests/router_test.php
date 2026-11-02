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

use aiplacement_assistant\intent\general_help;

/**
 * Tests for the router.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \aiplacement_assistant\router
 */
final class router_test extends \advanced_testcase {
    /**
     * Load fixtures.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/ai/placement/assistant/tests/fixtures/fake_ai_bridge.php');
        require_once($CFG->dirroot . '/ai/placement/assistant/tests/fixtures/fake_grade_intent.php');
    }

    /**
     * Intents for tests.
     *
     * @return array
     */
    private function intents(): array {
        return ['general_help' => new general_help(), 'my_grade' => new fake_grade_intent()];
    }

    /**
     * Plain JSON parses.
     */
    public function test_parse_plain_json(): void {
        $out = (new router())->parse('{"intent":"general_help","params":{}}', $this->intents());
        $this->assertSame(['intent' => 'general_help', 'params' => []], $out);
    }

    /**
     * Fenced JSON with prose parses.
     */
    public function test_parse_fenced_json_with_prose(): void {
        $fence = str_repeat(chr(96), 3);
        $raw = "Sure!\n{$fence}json\n{\"intent\":\"my_grade\",\"params\":{\"course\":\"Chem\"}}\n{$fence}";
        $out = (new router())->parse($raw, $this->intents());
        $this->assertSame(['intent' => 'my_grade', 'params' => ['course' => 'Chem']], $out);
    }

    /**
     * Unknown intent rejected.
     */
    public function test_parse_unknown_intent_returns_null(): void {
        $this->assertNull((new router())->parse('{"intent":"nope","params":{}}', $this->intents()));
    }

    /**
     * Undeclared params dropped.
     */
    public function test_parse_drops_undeclared_params(): void {
        $raw = '{"intent":"my_grade","params":{"course":"Chem","evil":"x"}}';
        $out = (new router())->parse($raw, $this->intents());
        $this->assertSame(['course' => 'Chem'], $out['params']);
    }

    /**
     * Garbage rejected.
     */
    public function test_parse_garbage_returns_null(): void {
        $this->assertNull((new router())->parse("I don't know", $this->intents()));
    }

    /**
     * Non-scalar, empty and typed params are handled.
     */
    public function test_parse_param_cleaning(): void {
        $raw = '{"intent":"my_grade","params":{"course":["a"],"limit":"7abc"}}';
        $out = (new router())->parse($raw, $this->intents());
        $this->assertSame(['limit' => 7], $out['params']);
        $raw = '{"intent":"my_grade","params":{"course":"","limit":5}}';
        $out = (new router())->parse($raw, $this->intents());
        $this->assertSame(['limit' => 5], $out['params']);
    }

    /**
     * Missing or non-object params give an empty list.
     */
    public function test_parse_missing_params(): void {
        $router = new router();
        $this->assertSame(['intent' => 'my_grade', 'params' => []], $router->parse('{"intent":"my_grade"}', $this->intents()));
        $this->assertSame(
            ['intent' => 'my_grade', 'params' => []],
            $router->parse('{"intent":"my_grade","params":"x"}', $this->intents())
        );
    }

    /**
     * Prompt limits history and describes params.
     */
    public function test_build_prompt(): void {
        $history = [];
        for ($i = 1; $i <= 8; $i++) {
            $history[] = ['role' => 'user', 'text' => "msg{$i}x"];
        }
        $p = (new router())->build_prompt('hello', $history, $this->intents(), 'en');
        $this->assertStringStartsWith(router::FRAMING, $p);
        $this->assertStringNotContainsString('msg2x', $p);
        $this->assertStringContainsString('msg3x', $p);
        $this->assertStringContainsString('course (', $p);
        $this->assertStringContainsString('<<<DATA history>>>', $p);
    }

    /**
     * The router prompt frames the job as classification, treats commands as normal and lists general_help last.
     */
    public function test_build_prompt_classify_framing(): void {
        $intents = ['general_help' => new general_help(), 'my_grade' => new fake_grade_intent()];
        $p = (new router())->build_prompt('Summarise the forum', [], $intents, 'en');
        $this->assertStringContainsString('Your only job is to classify', $p);
        $this->assertStringContainsString('Requests phrased as commands', $p);
        $this->assertStringContainsString('"this course"', $p);
        // The general_help line comes after every other intent and says it is the last resort.
        $this->assertGreaterThan(strpos($p, "\nmy_grade: "), strpos($p, "\ngeneral_help: "));
        $this->assertMatchesRegularExpression('/general_help: .*only if no other intent fits/', $p);
        // The reply instruction is the last thing in the prompt.
        $this->assertGreaterThan(strpos($p, '<<<END message>>>'), strpos($p, 'Reply with only JSON'));
        $this->assertStringNotContainsString('Use general_help for how-to questions or anything else.', $p);
    }

    /**
     * Every real intent gives an example request in its description.
     */
    public function test_real_intents_have_examples(): void {
        foreach (\aiplacement_assistant\intent\registry::INTENTS as $class) {
            $this->assertStringContainsString('e.g. "', $class::description(), $class);
        }
    }

    /**
     * Routing uses the AI bridge.
     */
    public function test_route_uses_fake_ai(): void {
        $this->resetAfterTest();
        $fake = new fake_ai_bridge();
        $fake->responses = ['{"intent":"my_grade","params":{"course":"Chem"}}'];
        \core\di::set(ai_bridge::class, $fake);
        $r = new request(\context_system::instance(), 2, 'what is my grade');
        $out = (new router())->route($r, [], $this->intents());
        $this->assertSame(['intent' => 'my_grade', 'params' => ['course' => 'Chem']], $out);
        $this->assertStringContainsString('<<<DATA message>>>', $fake->prompts[0]);
    }

    /**
     * Null AI reply gives null.
     */
    public function test_route_null_when_ai_null(): void {
        $this->resetAfterTest();
        $fake = new fake_ai_bridge();
        \core\di::set(ai_bridge::class, $fake);
        $r = new request(\context_system::instance(), 2, 'hi');
        $this->assertNull((new router())->route($r, [], $this->intents()));
    }
}
