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

namespace aiplacement_assistant\privacy;

use aiplacement_assistant\conversation;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the privacy provider.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Create two users with messages in one course context each.
     *
     * @return array
     */
    private function setup_data(): array {
        $this->resetAfterTest();
        set_config('retentiondays', 30, 'aiplacement_assistant');
        $gen = $this->getDataGenerator();
        $u1 = $gen->create_user();
        $u2 = $gen->create_user();
        $ctx = \context_course::instance($gen->create_course()->id);
        $sys = \context_system::instance();
        (new conversation($u1->id))->add('user', 'hello', $ctx->id, 'general_help');
        (new conversation($u1->id))->add('assistant', 'hi there', $sys->id);
        (new conversation($u2->id))->add('user', 'u2 msg', $ctx->id);
        return [$u1, $u2, $ctx, $sys];
    }

    /**
     * Metadata describes the table and external location.
     */
    public function test_metadata(): void {
        $items = provider::get_metadata(new collection('aiplacement_assistant'))->get_collection();
        $names = array_map(fn($i) => $i->get_name(), $items);
        $this->assertContains('aiplacement_assistant_msg', $names);
        $this->assertContains('aiprovider', $names);
    }

    /**
     * Contexts are those of the user's rows.
     */
    public function test_get_contexts_for_userid(): void {
        [$u1, $u2, $ctx, $sys] = $this->setup_data();
        $list = provider::get_contexts_for_userid($u1->id);
        $this->assertEqualsCanonicalizing([$ctx->id, $sys->id], $list->get_contextids());
        $this->assertEquals([$ctx->id], provider::get_contexts_for_userid($u2->id)->get_contextids());
    }

    /**
     * Export then delete for a user.
     */
    public function test_export_and_delete_user_data(): void {
        global $DB;
        [$u1, $u2, $ctx, $sys] = $this->setup_data();
        $list = new approved_contextlist($u1, 'aiplacement_assistant', [$ctx->id, $sys->id]);
        provider::export_user_data($list);
        $data = writer::with_context($ctx)->get_data([get_string('pluginname', 'aiplacement_assistant')]);
        $this->assertNotEmpty($data);
        $this->assertCount(1, $data->messages);
        $this->assertSame('hello', $data->messages[0]['message']);

        provider::delete_data_for_user($list);
        $this->assertSame(0, $DB->count_records('aiplacement_assistant_msg', ['userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('aiplacement_assistant_msg', ['userid' => $u2->id]));
    }

    /**
     * Delete all users in a context.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        [$u1, $u2, $ctx, $sys] = $this->setup_data();
        provider::delete_data_for_all_users_in_context($ctx);
        $this->assertSame(1, $DB->count_records('aiplacement_assistant_msg'));
    }

    /**
     * Userlist operations.
     */
    public function test_userlist(): void {
        global $DB;
        [$u1, $u2, $ctx, $sys] = $this->setup_data();
        $userlist = new userlist($ctx, 'aiplacement_assistant');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$u1->id, $u2->id], $userlist->get_userids());

        provider::delete_data_for_users(new approved_userlist($ctx, 'aiplacement_assistant', [$u1->id]));
        $this->assertSame(0, $DB->count_records('aiplacement_assistant_msg', ['userid' => $u1->id, 'contextid' => $ctx->id]));
        $this->assertSame(1, $DB->count_records('aiplacement_assistant_msg', ['userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('aiplacement_assistant_msg', ['userid' => $u2->id]));
    }
}
