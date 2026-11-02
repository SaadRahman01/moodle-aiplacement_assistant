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

namespace aiplacement_assistant\output;

use aiplacement_assistant\ai_bridge;
use aiplacement_assistant\fake_ai_bridge;
use core\hook\output\before_footer_html_generation;

/**
 * Tests for the chat UI loader.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \aiplacement_assistant\output\chat_ui
 * @covers \aiplacement_assistant\hook_callbacks
 */
final class chat_ui_test extends \advanced_testcase {
    /** @var fake_ai_bridge The AI bridge test double. */
    private fake_ai_bridge $fake;

    /**
     * Load fixtures.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/ai/placement/assistant/tests/fixtures/fake_ai_bridge.php');
    }

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->fake = new fake_ai_bridge();
        \core\di::set(ai_bridge::class, $this->fake);
    }

    /**
     * Build a page for a course.
     *
     * @param \stdClass $course The course.
     * @param string $pagetype Page type.
     * @param string $layout Page layout.
     * @return \moodle_page
     */
    private function make_page(
        \stdClass $course,
        string $pagetype = 'course-view-topics',
        string $layout = 'course'
    ): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\context_course::instance($course->id));
        $page->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
        $page->set_pagetype($pagetype);
        $page->set_pagelayout($layout);
        return $page;
    }

    /**
     * Create a course with an enrolled student and log in as them.
     *
     * @return \stdClass The course.
     */
    private function student_course(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);
        return $course;
    }

    /**
     * Guests never see the assistant.
     */
    public function test_hidden_for_guest(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->setGuestUser();
        $this->assertFalse(chat_ui::should_show($this->make_page($course)));
    }

    /**
     * Not logged in users never see the assistant.
     */
    public function test_hidden_when_not_logged_in(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->setUser(null);
        $this->assertFalse(chat_ui::should_show($this->make_page($course)));
    }

    /**
     * Hidden on quiz attempt and summary pages.
     *
     * @dataProvider exam_pagetypes
     * @param string $pagetype Page type.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('exam_pagetypes')]
    public function test_hidden_on_quiz_attempt(string $pagetype): void {
        $course = $this->student_course();
        $this->assertFalse(chat_ui::should_show($this->make_page($course, $pagetype, 'incourse')));
    }

    /**
     * Quiz page types where the assistant must not be shown.
     *
     * @return array
     */
    public static function exam_pagetypes(): array {
        return [
            'attempt' => ['mod-quiz-attempt'],
            'summary' => ['mod-quiz-summary'],
        ];
    }

    /**
     * Hidden on the secure layout (safe exam browser / popup quiz).
     */
    public function test_hidden_on_secure_layout(): void {
        $course = $this->student_course();
        $this->assertFalse(chat_ui::should_show($this->make_page($course, 'mod-quiz-review', 'secure')));
    }

    /**
     * Hidden when the AI subsystem says the assistant is unavailable.
     */
    public function test_hidden_when_unavailable(): void {
        $course = $this->student_course();
        $this->fake->available = false;
        $this->assertFalse(chat_ui::should_show($this->make_page($course)));
    }

    /**
     * Shown to a student on a course page.
     */
    public function test_shown_for_student_on_course_page(): void {
        $course = $this->student_course();
        $this->assertTrue(chat_ui::should_show($this->make_page($course)));
    }

    /**
     * The quiz view page (not an attempt) still shows the assistant.
     */
    public function test_shown_on_quiz_view(): void {
        $course = $this->student_course();
        $this->assertTrue(chat_ui::should_show($this->make_page($course, 'mod-quiz-view', 'incourse')));
    }

    /**
     * On Moodle 5.0+ the button is hidden in a course with AI tools turned off (real bridge, no fake).
     */
    public function test_hidden_when_course_ai_tools_disabled(): void {
        if (!method_exists(\core_ai\manager::class, 'is_action_enabled_in_context')) {
            $this->markTestSkipped('Per-course AI tools setting requires Moodle 5.0 or later.');
        }
        \core\di::set(ai_bridge::class, new ai_bridge());
        \core\di::get(\core_ai\manager::class)->create_provider_instance(
            classname: '\aiprovider_openai\provider',
            name: 'x',
            enabled: true,
            config: ['apikey' => 'x'],
        );
        \core\plugininfo\aiplacement::enable_plugin('assistant', 1);
        $on = $this->getDataGenerator()->create_course(['enableaitools' => 1]);
        $off = $this->getDataGenerator()->create_course(['enableaitools' => 0]);
        $user = $this->getDataGenerator()->create_and_enrol($on, 'student');
        $this->getDataGenerator()->enrol_user($user->id, $off->id, 'student');
        $this->setUser($user);
        $this->assertTrue(chat_ui::should_show($this->make_page($on)));
        $this->assertFalse(chat_ui::should_show($this->make_page($off)));
    }

    /**
     * The footer hook renders the panel markup when shown.
     */
    public function test_hook_adds_panel(): void {
        global $PAGE;
        $course = $this->student_course();
        $PAGE = $this->make_page($course);
        $hook = new before_footer_html_generation($PAGE->get_renderer('core'));
        \aiplacement_assistant\hook_callbacks::before_footer_html_generation($hook);
        $html = $hook->get_output();
        $this->assertStringContainsString('data-region="aiplacement_assistant-panel"', $html);
        $this->assertStringContainsString(get_string('openassistant', 'aiplacement_assistant'), $html);
    }

    /**
     * The footer hook adds nothing when hidden.
     */
    public function test_hook_adds_nothing_when_hidden(): void {
        global $PAGE;
        $course = $this->student_course();
        $this->fake->available = false;
        $PAGE = $this->make_page($course);
        $hook = new before_footer_html_generation($PAGE->get_renderer('core'));
        \aiplacement_assistant\hook_callbacks::before_footer_html_generation($hook);
        $this->assertSame('', $hook->get_output());
    }
}
