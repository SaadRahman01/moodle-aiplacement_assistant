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
 * The current user's own course total grade.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class my_grade extends intent_base {
    #[\Override]
    public static function name(): string {
        return 'my_grade';
    }

    #[\Override]
    public static function description(): string {
        return 'The user\'s own overall grade in a course, e.g. "What is my grade in Biology?"';
    }

    #[\Override]
    public static function params(): array {
        return [
            'course' => [
                'type' => PARAM_TEXT,
                'description' => 'Course name or short name; leave empty if the user means the current course',
            ],
        ];
    }

    #[\Override]
    public function is_available(request $r): bool {
        return $r->userid > 0 && !isguestuser($r->userid);
    }

    #[\Override]
    public function handle(array $params, request $r): answer {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/querylib.php');

        $courses = lookup::resolve_courses($r, (string) ($params['course'] ?? ''));
        if (!$courses) {
            return new answer(fallback: get_string('notfound_course', 'aiplacement_assistant'));
        }
        if (count($courses) > 1) {
            return new answer(
                fallback: get_string('pick_course', 'aiplacement_assistant'),
                rows: lookup::course_candidate_rows($courses),
            );
        }

        $course = reset($courses);
        $context = \context_course::instance($course->id);
        if (!has_capability('moodle/grade:view', $context, $r->userid)) {
            return new answer(fallback: get_string('notfound_course', 'aiplacement_assistant'));
        }

        $name = format_string($course->fullname, true, ['context' => $context, 'escape' => false]);
        $grade = grade_get_course_grade($r->userid, $course->id);
        if (!$grade || !$course->showgrades) {
            $value = get_string('grade_hidden', 'aiplacement_assistant');
        } else if ($grade->hidden && !has_capability('moodle/grade:viewhidden', $context, $r->userid)) {
            $value = get_string('grade_hidden', 'aiplacement_assistant');
        } else if (
            !has_capability('moodle/grade:viewhidden', $context, $r->userid)
            && $this->total_affected_by_hidden($r->userid, $course->id)
        ) {
            $value = get_string('grade_hidden', 'aiplacement_assistant');
        } else {
            $value = $grade->str_long_grade;
        }

        return new answer(
            facts: ['course' => $name],
            fallback: get_string('grade_found', 'aiplacement_assistant', $name),
            rows: [[
                'label' => $name,
                'value' => $value,
                'url' => (new \moodle_url('/grade/report/user/index.php', ['id' => $course->id]))->out(false),
            ]],
        );
    }

    /**
     * Whether the course total is affected by items hidden from the user, following the user report rules.
     *
     * Returns false when the course is set to show the real total even if it contains hidden items.
     *
     * @param int $userid The user.
     * @param int $courseid The course.
     * @return bool
     */
    private function total_affected_by_hidden(int $userid, int $courseid): bool {
        global $CFG, $DB;

        $setting = grade_get_setting(
            $courseid,
            'report_user_showtotalsifcontainhidden',
            $CFG->grade_report_user_showtotalsifcontainhidden
        );
        if ((int) $setting === GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN) {
            return false;
        }

        $items = \grade_item::fetch_all(['courseid' => $courseid]);
        if (!$items) {
            return false;
        }
        $grades = [];
        $records = $DB->get_records_sql(
            "SELECT g.*
               FROM {grade_grades} g
               JOIN {grade_items} gi ON gi.id = g.itemid
              WHERE g.userid = :userid AND gi.courseid = :courseid",
            ['userid' => $userid, 'courseid' => $courseid]
        );
        foreach ($records as $record) {
            $grades[$record->itemid] = new \grade_grade($record, false);
        }
        foreach ($items as $itemid => $unused) {
            if (!isset($grades[$itemid])) {
                $grades[$itemid] = new \grade_grade(['userid' => $userid, 'itemid' => $itemid], false);
            }
            $grades[$itemid]->grade_item = &$items[$itemid];
        }

        $courseitem = \grade_item::fetch_course_item($courseid);
        $affected = \grade_grade::get_hiding_affected($grades, $items);
        // A calculated or locked total that depends on hidden items is "unknown"; the user report blanks it too.
        $keys = ['unknowngrades', 'altered', 'alteredgrademin', 'alteredgrademax', 'alteredaggregationstatus',
            'alteredaggregationweight'];
        foreach ($keys as $key) {
            if (array_key_exists($courseitem->id, $affected[$key])) {
                return true;
            }
        }
        return false;
    }
}
