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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for aiplacement_assistant.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string Table name. */
    private const TABLE = 'aiplacement_assistant_msg';

    /**
     * Describe stored and exported data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE, [
            'userid' => 'privacy:metadata:aiplacement_assistant_msg:userid',
            'contextid' => 'privacy:metadata:aiplacement_assistant_msg:contextid',
            'role' => 'privacy:metadata:aiplacement_assistant_msg:role',
            'message' => 'privacy:metadata:aiplacement_assistant_msg:message',
            'intent' => 'privacy:metadata:aiplacement_assistant_msg:intent',
            'timecreated' => 'privacy:metadata:aiplacement_assistant_msg:timecreated',
        ], 'privacy:metadata:aiplacement_assistant_msg');

        $collection->link_external_location('aiprovider', [
            'message' => 'privacy:metadata:aiprovider:message',
            'pagehelp' => 'privacy:metadata:aiprovider:pagehelp',
        ], 'privacy:metadata:aiprovider');

        return $collection;
    }

    /**
     * Contexts containing messages of the user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT DISTINCT contextid FROM {' . self::TABLE . '} WHERE userid = :userid',
            ['userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Users with messages in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {' . self::TABLE . '} WHERE contextid = :contextid',
            ['contextid' => $userlist->get_context()->id]
        );
    }

    /**
     * Export the user's messages, one subcontext path per context.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!$contextlist->count()) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        [$insql, $inparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $rows = $DB->get_recordset_select(
            self::TABLE,
            "userid = :userid AND contextid $insql",
            ['userid' => $userid] + $inparams,
            'timecreated ASC, id ASC'
        );
        $bycontext = [];
        foreach ($rows as $r) {
            $bycontext[$r->contextid][] = [
                'role' => $r->role,
                'message' => $r->message,
                'intent' => (string) $r->intent,
                'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated),
            ];
        }
        $rows->close();
        $subcontext = [get_string('pluginname', 'aiplacement_assistant')];
        foreach ($bycontext as $contextid => $messages) {
            writer::with_context(\context::instance_by_id($contextid))
                ->export_data($subcontext, (object) ['messages' => $messages]);
        }
    }

    /**
     * Delete all users' messages in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['contextid' => $context->id]);
    }

    /**
     * Delete a user's messages in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (!$contextlist->count()) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            self::TABLE,
            "userid = :userid AND contextid $insql",
            ['userid' => $contextlist->get_user()->id] + $inparams
        );
    }

    /**
     * Delete messages of approved users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            self::TABLE,
            "contextid = :contextid AND userid $insql",
            ['contextid' => $userlist->get_context()->id] + $inparams
        );
    }
}
