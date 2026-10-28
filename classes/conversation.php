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
 * Stores and reads a user's assistant conversation.
 *
 * With retention above zero, messages live in the database; with zero they are
 * kept only in the user's session (last six messages).
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversation {
    /** @var int Maximum messages kept in session mode. */
    private const SESSION_CAP = 6;

    /** @var string[] Allowed roles. */
    private const ROLES = ['user', 'assistant'];

    /**
     * Constructor.
     *
     * @param int $userid
     */
    public function __construct(
        /** @var int $userid The user whose conversation this is. */
        private readonly int $userid
    ) {
    }

    /**
     * Whether messages persist in the database.
     *
     * @return bool
     */
    private static function persistent(): bool {
        return max(0, (int) get_config('aiplacement_assistant', 'retentiondays')) > 0;
    }

    /**
     * The most recent messages, oldest first.
     *
     * @param int $limit
     * @return array list of ['role' => string, 'text' => string]
     */
    public function recent(int $limit = 6): array {
        return array_map(
            fn($m) => ['role' => $m['role'], 'text' => $m['text']],
            $this->fetch($limit)
        );
    }

    /**
     * Messages for display in the panel, oldest first.
     *
     * @param int $limit
     * @return array list of ['role', 'text', 'intent', 'timecreated']
     */
    public function history(int $limit = 50): array {
        return $this->fetch($limit);
    }

    /**
     * Add a message.
     *
     * @param string $role user or assistant
     * @param string $text plain text only (never data rows)
     * @param int $contextid
     * @param string $intent
     */
    public function add(string $role, string $text, int $contextid, string $intent = ''): void {
        global $DB, $SESSION;
        if (!in_array($role, self::ROLES, true)) {
            throw new \coding_exception('Invalid conversation role: ' . $role);
        }
        $now = time();
        if (self::persistent()) {
            $DB->insert_record('aiplacement_assistant_msg', (object) [
                'userid' => $this->userid,
                'contextid' => $contextid,
                'role' => $role,
                'message' => $text,
                'intent' => $intent === '' ? null : $intent,
                'timecreated' => $now,
            ]);
            return;
        }
        $list = $SESSION->aiplacement_assistant_history ?? [];
        $list[] = ['role' => $role, 'text' => $text, 'intent' => $intent, 'timecreated' => $now];
        $SESSION->aiplacement_assistant_history = array_slice($list, -self::SESSION_CAP);
    }

    /**
     * Delete stored messages older than a timestamp.
     *
     * @param int $timestamp
     * @return int number of rows deleted
     */
    public static function purge_older_than(int $timestamp): int {
        global $DB;
        $count = $DB->count_records_select('aiplacement_assistant_msg', 'timecreated < ?', [$timestamp]);
        $DB->delete_records_select('aiplacement_assistant_msg', 'timecreated < ?', [$timestamp]);
        return $count;
    }

    /**
     * Fetch the latest messages, oldest first.
     *
     * @param int $limit
     * @return array
     */
    private function fetch(int $limit): array {
        global $DB, $SESSION;
        if ($limit <= 0) {
            return [];
        }
        if (!self::persistent()) {
            return array_slice($SESSION->aiplacement_assistant_history ?? [], -$limit);
        }
        $rows = $DB->get_records(
            'aiplacement_assistant_msg',
            ['userid' => $this->userid],
            'timecreated DESC, id DESC',
            'id, role, message, intent, timecreated',
            0,
            $limit
        );
        $out = [];
        foreach (array_reverse($rows) as $r) {
            $out[] = [
                'role' => $r->role,
                'text' => $r->message,
                'intent' => (string) $r->intent,
                'timecreated' => (int) $r->timecreated,
            ];
        }
        return $out;
    }
}
