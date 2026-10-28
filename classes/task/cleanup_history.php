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

namespace aiplacement_assistant\task;

use aiplacement_assistant\conversation;

/**
 * Deletes assistant history older than the retention period.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_history extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_history', 'aiplacement_assistant');
    }

    /**
     * Run the task.
     */
    public function execute(): void {
        $days = (int) get_config('aiplacement_assistant', 'retentiondays');
        // Retention zero means session-only: nothing may remain stored.
        $cutoff = $days > 0 ? time() - $days * DAYSECS : PHP_INT_MAX;
        $deleted = conversation::purge_older_than($cutoff);
        mtrace("aiplacement_assistant: deleted $deleted old message(s).");
    }
}
