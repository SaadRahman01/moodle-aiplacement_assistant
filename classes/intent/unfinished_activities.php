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
 * Activities the current user has not yet completed in a course.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unfinished_activities extends intent_base {
    #[\Override]
    public static function name(): string {
        return 'unfinished_activities';
    }

    #[\Override]
    public static function description(): string {
        return 'Which activities the user has not completed yet in a course, e.g. "What do I still need to finish?"';
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
        require_once($CFG->libdir . '/completionlib.php');

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
        $name = format_string($course->fullname, true, ['context' => \context_course::instance($course->id), 'escape' => false]);
        $completion = new \completion_info(get_course($course->id));
        if (!$completion->is_enabled()) {
            return new answer(fallback: get_string('completion_off', 'aiplacement_assistant'));
        }

        $rows = [];
        $modinfo = get_fast_modinfo($course->id, $r->userid);
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || $completion->is_enabled($cm) == COMPLETION_TRACKING_NONE) {
                continue;
            }
            $data = $completion->get_data($cm, false, $r->userid);
            if ((int) $data->completionstate !== COMPLETION_INCOMPLETE) {
                continue;
            }
            $url = $cm->url;
            $rows[] = [
                'label' => $cm->get_formatted_name(['escape' => false]),
                'value' => '',
                'url' => $url ? $url->out(false) : null,
            ];
        }

        $count = count($rows);
        return new answer(
            facts: ['count' => $count, 'course' => $name],
            fallback: $count
                ? get_string('unfinished_count', 'aiplacement_assistant', (object) ['count' => $count, 'course' => $name])
                : get_string('all_done', 'aiplacement_assistant', $name),
            rows: $rows,
        );
    }
}
