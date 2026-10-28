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
 * Tests for conversation storage.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(conversation::class)]
final class conversation_test extends \advanced_testcase {
    /**
     * Recent returns the last six messages, oldest first.
     */
    public function test_recent_returns_last_six_in_order(): void {
        $this->resetAfterTest();
        set_config('retentiondays', 30, 'aiplacement_assistant');
        $user = $this->getDataGenerator()->create_user();
        $conv = new conversation($user->id);
        for ($i = 1; $i <= 8; $i++) {
            $conv->add($i % 2 ? 'user' : 'assistant', "m$i", 1);
        }
        $recent = $conv->recent();
        $this->assertCount(6, $recent);
        $this->assertSame(['role' => 'user', 'text' => 'm3'], $recent[0]);
        $this->assertSame('m8', $recent[5]['text']);
    }

    /**
     * Retention zero keeps data in the session only.
     */
    public function test_session_mode_when_retention_zero(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('retentiondays', 0, 'aiplacement_assistant');
        $user = $this->getDataGenerator()->create_user();
        $conv = new conversation($user->id);
        for ($i = 1; $i <= 8; $i++) {
            $conv->add('user', "m$i", 1);
        }
        $this->assertSame(0, $DB->count_records('aiplacement_assistant_msg'));
        $recent = $conv->recent();
        $this->assertCount(6, $recent);
        $this->assertSame('m3', $recent[0]['text']);
        $this->assertCount(6, $conv->history());
    }

    /**
     * Negative retention behaves as zero.
     */
    public function test_negative_retention_is_session_mode(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('retentiondays', -5, 'aiplacement_assistant');
        $conv = new conversation($this->getDataGenerator()->create_user()->id);
        $conv->add('user', 'x', 1);
        $this->assertSame(0, $DB->count_records('aiplacement_assistant_msg'));
    }

    /**
     * Invalid role is rejected.
     */
    public function test_invalid_role(): void {
        $this->resetAfterTest();
        $conv = new conversation($this->getDataGenerator()->create_user()->id);
        $this->expectException(\coding_exception::class);
        $conv->add('system', 'x', 1);
    }

    /**
     * History is per user, oldest first, limited.
     */
    public function test_history_limit_and_isolation(): void {
        $this->resetAfterTest();
        set_config('retentiondays', 30, 'aiplacement_assistant');
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $c1 = new conversation($u1->id);
        $c2 = new conversation($u2->id);
        for ($i = 1; $i <= 5; $i++) {
            $c1->add('user', "a$i", 1, 'general_help');
        }
        $c2->add('user', 'other', 1);
        $hist = $c1->history(3);
        $this->assertCount(3, $hist);
        $this->assertSame('a3', $hist[0]['text']);
        $this->assertSame('a5', $hist[2]['text']);
        $this->assertSame('user', $hist[0]['role']);
    }

    /**
     * Purge deletes only old rows.
     */
    public function test_purge(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('retentiondays', 30, 'aiplacement_assistant');
        $user = $this->getDataGenerator()->create_user();
        $conv = new conversation($user->id);
        $conv->add('user', 'old', 1);
        $conv->add('user', 'new', 1);
        $old = $DB->get_record_select('aiplacement_assistant_msg', $DB->sql_compare_text('message') . ' = ?', ['old']);
        $DB->set_field('aiplacement_assistant_msg', 'timecreated', time() - 100 * DAYSECS, ['id' => $old->id]);
        $this->assertSame(1, conversation::purge_older_than(time() - 30 * DAYSECS));
        $this->assertSame(1, $DB->count_records('aiplacement_assistant_msg'));
    }
}
