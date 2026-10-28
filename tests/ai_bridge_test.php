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

use core_ai\aiactions\responses\response_generate_text;

/**
 * Tests for the AI bridge.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ai_bridge::class)]
final class ai_bridge_test extends \advanced_testcase {
    /**
     * Create and enable an OpenAI provider using the version-appropriate API.
     */
    private function create_provider(): void {
        global $CFG;
        if ($CFG->branch >= 500) {
            \core\di::get(\core_ai\manager::class)->create_provider_instance(
                classname: '\aiprovider_openai\provider',
                name: 'x',
                enabled: true,
                config: ['apikey' => 'x'],
            );
        } else {
            set_config('apikey', 'x', 'aiprovider_openai');
            \core\plugininfo\aiprovider::enable_plugin('openai', 1);
        }
    }

    /**
     * Enable the placement plugin.
     */
    private function enable_placement(): void {
        \core\plugininfo\aiplacement::enable_plugin('assistant', 1);
    }

    /**
     * Install a mocked core AI manager.
     *
     * @param callable $impl Implementation of process_action.
     */
    private function mock_manager(callable $impl): void {
        $mock = $this->getMockBuilder(\core_ai\manager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['process_action'])
            ->getMock();
        $mock->method('process_action')->willReturnCallback($impl);
        \core\di::set(\core_ai\manager::class, $mock);
    }

    /**
     * No provider means unavailable.
     */
    public function test_unavailable_when_no_provider(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->enable_placement();
        $this->assertFalse(\core\di::get(ai_bridge::class)->is_available(\context_system::instance()));
    }

    /**
     * Without the capability the assistant is unavailable.
     */
    public function test_unavailable_without_capability(): void {
        $this->resetAfterTest();
        $this->enable_placement();
        $this->create_provider();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('aiplacement/assistant:use', CAP_PROHIBIT, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        $this->assertFalse(\core\di::get(ai_bridge::class)->is_available(\context_system::instance()));
    }

    /**
     * Fully configured means available.
     */
    public function test_available_when_configured(): void {
        $this->resetAfterTest();
        $this->enable_placement();
        $this->create_provider();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('aiplacement/assistant:use', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        $this->assertTrue(\core\di::get(ai_bridge::class)->is_available(\context_system::instance()));
    }

    /**
     * Moodle 5.0+ course-level AI tools switch is respected.
     *
     * @dataProvider course_ai_tools_provider
     * @param int $enableaitools Course enableaitools value.
     * @param bool $expected Expected availability.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('course_ai_tools_provider')]
    public function test_course_ai_tools_setting(int $enableaitools, bool $expected): void {
        if (!method_exists(\core_ai\manager::class, 'is_action_enabled_in_context')) {
            $this->markTestSkipped('Per-course AI tools setting requires Moodle 5.0 or later.');
        }
        $this->resetAfterTest();
        $this->enable_placement();
        $this->create_provider();
        $course = $this->getDataGenerator()->create_course(['enableaitools' => $enableaitools]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $this->assertSame($expected, \core\di::get(ai_bridge::class)->is_available(\context_course::instance($course->id)));
    }

    /**
     * Course AI tools values.
     *
     * @return array
     */
    public static function course_ai_tools_provider(): array {
        return [
            'disabled' => [0, false],
            'enabled' => [1, true],
        ];
    }

    /**
     * Policy status is delegated to core.
     */
    public function test_policy_accepted_false_by_default(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(\core\di::get(ai_bridge::class)->policy_accepted((int) $user->id));
    }

    /**
     * A throwing manager yields null.
     */
    public function test_generate_returns_null_when_manager_throws(): void {
        $this->resetAfterTest();
        $this->mock_manager(function () {
            throw new \moodle_exception('generalexceptionmessage', 'error', '', 'boom');
        });
        $result = \core\di::get(ai_bridge::class)->generate(\context_system::instance(), 2, 'hi');
        $this->assertNull($result);
        $this->assertDebuggingCalled();
    }

    /**
     * An unsuccessful response yields null.
     */
    public function test_generate_returns_null_on_unsuccessful_response(): void {
        $this->resetAfterTest();
        // Newer cores also require an error name, so build the arguments from the constructor signature.
        $args = ['success' => false, 'errorcode' => 500, 'errormessage' => 'x'];
        $ctor = new \ReflectionMethod(response_generate_text::class, '__construct');
        $params = array_map(fn($p) => $p->getName(), $ctor->getParameters());
        if (in_array('error', $params, true)) {
            $args['error'] = 'x';
        }
        $this->mock_manager(fn() => new response_generate_text(...$args));
        $this->assertNull(\core\di::get(ai_bridge::class)->generate(\context_system::instance(), 2, 'hi'));
    }

    /**
     * A successful response returns the generated content.
     */
    public function test_generate_returns_content_on_success(): void {
        $this->resetAfterTest();
        $this->mock_manager(function (\core_ai\aiactions\base $action) {
            $response = new response_generate_text(success: true);
            $response->set_response_data(['generatedcontent' => 'hello']);
            return $response;
        });
        $this->assertSame('hello', \core\di::get(ai_bridge::class)->generate(\context_system::instance(), 2, 'hi'));
    }

    /**
     * The fixture records prompts and replays queued responses.
     */
    public function test_fake_bridge(): void {
        $this->resetAfterTest();
        require_once(__DIR__ . '/fixtures/fake_ai_bridge.php');
        $fake = new fake_ai_bridge();
        $fake->responses = ['a'];
        \core\di::set(ai_bridge::class, $fake);
        $bridge = \core\di::get(ai_bridge::class);
        $this->assertSame('a', $bridge->generate(\context_system::instance(), 2, 'p1'));
        $this->assertNull($bridge->generate(\context_system::instance(), 2, 'p2'));
        $this->assertSame(['p1', 'p2'], $fake->prompts);
        $this->assertTrue($bridge->is_available(\context_system::instance()));
        $this->assertTrue($bridge->policy_accepted(2));
    }
}
