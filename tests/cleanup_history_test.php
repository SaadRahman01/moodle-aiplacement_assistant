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
 * Tests for the cleanup task.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(task\cleanup_history::class)]
final class cleanup_history_test extends \advanced_testcase {
    /**
     * Old rows are removed according to retention.
     */
    public function test_execute_purges_old_rows(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('retentiondays', 10, 'aiplacement_assistant');
        $user = $this->getDataGenerator()->create_user();
        $conv = new conversation($user->id);
        $conv->add('user', 'old', 1);
        $oldid = $DB->get_field('aiplacement_assistant_msg', 'id', []);
        $conv->add('user', 'new', 1);
        $DB->set_field('aiplacement_assistant_msg', 'timecreated', time() - 11 * DAYSECS, ['id' => $oldid]);
        $this->expectOutputRegex('/deleted 1 old/');
        (new task\cleanup_history())->execute();
        $this->assertSame(['new'], array_values($DB->get_fieldset_select('aiplacement_assistant_msg', 'message', '1=1')));
    }

    /**
     * Retention zero skips purging.
     */
    public function test_skips_when_retention_zero(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('retentiondays', 10, 'aiplacement_assistant');
        $conv = new conversation($this->getDataGenerator()->create_user()->id);
        $conv->add('user', 'old', 1);
        $DB->set_field('aiplacement_assistant_msg', 'timecreated', time() - 99 * DAYSECS);
        set_config('retentiondays', 0, 'aiplacement_assistant');
        (new task\cleanup_history())->execute();
        $this->assertSame(1, $DB->count_records('aiplacement_assistant_msg'));
    }
}
