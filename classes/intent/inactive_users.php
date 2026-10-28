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

namespace aiplacement_assistant\intent;

use aiplacement_assistant\answer;
use aiplacement_assistant\request;

/**
 * Course participants who have not accessed the course recently.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class inactive_users extends intent_base {
    /** @var int Default window in days. */
    private const DEFAULT_DAYS = 14;

    #[\Override]
    public static function name(): string {
        return 'inactive_users';
    }

    #[\Override]
    public static function description(): string {
        return 'Which participants have not accessed this course recently (for teachers).';
    }

    #[\Override]
    public static function params(): array {
        return [
            'days' => ['type' => PARAM_INT, 'description' => 'Number of days without access, 1 to 365 (optional, default 14)'],
        ];
    }

    #[\Override]
    public function is_available(request $r): bool {
        $coursectx = $r->context->get_course_context(false);
        return $coursectx && $coursectx->instanceid != SITEID && self::can_view($coursectx, $r->userid);
    }

    /**
     * Whether the user may see course participation for the course.
     *
     * @param \context $coursectx Course context.
     * @param int $userid User id.
     * @return bool
     */
    private static function can_view(\context $coursectx, int $userid): bool {
        return has_capability('moodle/course:viewparticipants', $coursectx, $userid)
            && has_capability('report/participation:view', $coursectx, $userid);
    }

    #[\Override]
    public function handle(array $params, request $r): answer {
        global $CFG, $DB;

        $days = (int) ($params['days'] ?? self::DEFAULT_DAYS);
        if ($days < 1 || $days > 365) {
            $days = self::DEFAULT_DAYS;
        }
        $coursectx = $r->context->get_course_context(false);
        if (!$coursectx || !self::can_view($coursectx, $r->userid)) {
            return new answer(fallback: get_string('notfound_course', 'aiplacement_assistant'));
        }
        $hidden = array_map('trim', explode(',', (string) ($CFG->hiddenuserfields ?? '')));
        if (in_array('lastaccess', $hidden) && !has_capability('moodle/course:viewhiddenuserfields', $coursectx, $r->userid)) {
            return new answer(fallback: get_string('lastaccess_hidden', 'aiplacement_assistant'));
        }

        // Separate groups at course level limit the participants list to the user's own groups.
        $course = get_course($coursectx->instanceid);
        $groupids = 0;
        if (
            groups_get_course_groupmode($course) == SEPARATEGROUPS
            && !has_capability('moodle/site:accessallgroups', $coursectx, $r->userid)
        ) {
            $groupids = array_keys(groups_get_all_groups($course->id, $r->userid, $course->defaultgroupingid));
            if (!$groupids) {
                return $this->result([], $days, $course->id);
            }
        }

        [$enrolsql, $enrolparams] = get_enrolled_sql($coursectx, '', $groupids, true);
        $fields = implode(', ', array_map(fn($f) => 'u.' . $f, \core_user\fields::get_name_fields()));
        $sql = "SELECT u.id, {$fields}, ula.timeaccess
                  FROM {user} u
                  JOIN ({$enrolsql}) je ON je.id = u.id
             LEFT JOIN {user_lastaccess} ula ON ula.userid = u.id AND ula.courseid = :courseid
                 WHERE u.id <> :me
                   AND (ula.timeaccess IS NULL OR ula.timeaccess < :cutoff)
              ORDER BY COALESCE(ula.timeaccess, 0) ASC, u.lastname ASC, u.firstname ASC, u.id ASC";
        $users = $DB->get_records_sql($sql, $enrolparams + [
            'courseid' => $course->id,
            'me' => $r->userid,
            'cutoff' => time() - $days * DAYSECS,
        ]);
        return $this->result($users, $days, $course->id, has_capability('moodle/site:viewfullnames', $coursectx, $r->userid));
    }

    /**
     * Build the answer.
     *
     * @param array $users User records with timeaccess.
     * @param int $days Window in days.
     * @param int $courseid Course id.
     * @param bool $viewfullnames Whether full names may be shown.
     * @return answer
     */
    private function result(array $users, int $days, int $courseid, bool $viewfullnames = false): answer {
        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                'label' => fullname($user, $viewfullnames),
                'value' => $user->timeaccess
                    ? userdate($user->timeaccess, get_string('strftimedatetimeshort', 'langconfig'))
                    : get_string('never'),
                'url' => (new \moodle_url('/user/view.php', ['id' => $user->id, 'course' => $courseid]))->out(false),
            ];
        }
        $count = count($rows);
        $a = (object) ['count' => $count, 'days' => $days];
        return new answer(
            facts: ['count' => $count, 'days' => $days],
            fallback: $count
                ? get_string('inactive_count', 'aiplacement_assistant', $a)
                : get_string('inactive_none', 'aiplacement_assistant', $a),
            rows: $rows,
        );
    }
}
