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
        return 'The user\'s own overall grade in a course.';
    }

    #[\Override]
    public static function params(): array {
        return [
            'course' => ['type' => PARAM_TEXT, 'description' => 'Course name or short name (optional)'],
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

        $name = format_string($course->fullname, true, ['context' => $context]);
        $grade = grade_get_course_grade($r->userid, $course->id);
        if (!$grade || !$course->showgrades) {
            $value = get_string('grade_hidden', 'aiplacement_assistant');
        } else if ($grade->hidden && !has_capability('moodle/grade:viewhidden', $context, $r->userid)) {
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
}
