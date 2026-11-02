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

/**
 * Tests for the assistant placement.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \aiplacement_assistant\placement
 */
final class placement_test extends \advanced_testcase {
    /**
     * Only generate_text is supported.
     */
    public function test_action_list_is_generate_text_only(): void {
        $this->assertSame([\core_ai\aiactions\generate_text::class], (new placement())->get_action_list());
    }

    /**
     * Defaults are installed.
     */
    public function test_defaults_installed(): void {
        $this->resetAfterTest();
        $this->assertEquals(30, get_config('aiplacement_assistant', 'ratelimit'));
        $this->assertEquals(30, get_config('aiplacement_assistant', 'retentiondays'));
    }
}
