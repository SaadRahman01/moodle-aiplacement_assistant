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

use aiplacement_assistant\request;

/**
 * Shared lookup helpers for intents.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lookup {
    /**
     * Courses the user is actively enrolled in and may see, whose full or short name contains the term.
     *
     * An empty term matches every such course.
     *
     * @param int $userid The user.
     * @param string $term Search term (case-insensitive).
     * @return array Course records keyed by id.
     */
    public static function courses_for_user(int $userid, string $term): array {
        $term = trim($term);
        $result = [];
        $courses = enrol_get_users_courses($userid, true, 'id, fullname, shortname, visible, showgrades');
        foreach ($courses as $course) {
            if (!$course->visible) {
                $context = \context_course::instance($course->id);
                if (!has_capability('moodle/course:viewhiddencourses', $context, $userid)) {
                    continue;
                }
            }
            $haystack = \core_text::strtolower($course->fullname . "\n" . $course->shortname);
            if ($term !== '' && \core_text::strpos($haystack, \core_text::strtolower($term)) === false) {
                continue;
            }
            $result[$course->id] = $course;
        }
        \core_collator::asort_objects_by_property($result, 'fullname');
        return $result;
    }

    /**
     * Resolve the course(s) a request refers to.
     *
     * With an empty term and a request made from inside a course, that course is used.
     *
     * @param request $r The request.
     * @param string $term Course name term from the router.
     * @return array Matching course records keyed by id.
     */
    public static function resolve_courses(request $r, string $term): array {
        $term = trim($term);
        if ($term === '') {
            $coursecontext = $r->context->get_course_context(false);
            if (!$coursecontext) {
                return [];
            }
            $all = self::courses_for_user($r->userid, '');
            $id = $coursecontext->instanceid;
            return isset($all[$id]) ? [$id => $all[$id]] : [];
        }
        return self::courses_for_user($r->userid, $term);
    }

    /**
     * Candidate rows for an ambiguous course match.
     *
     * @param array $courses Course records.
     * @return array Rows.
     */
    public static function course_candidate_rows(array $courses): array {
        $rows = [];
        foreach ($courses as $course) {
            $rows[] = [
                'label' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                'value' => '',
                'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            ];
        }
        return $rows;
    }

    /**
     * Activities of one module type in a course that the user can see, whose name contains the term.
     *
     * With an empty term and a request context that is a module of that type, only that module is returned;
     * otherwise an empty term matches every visible activity of the type.
     *
     * @param \context $coursectx Course context, or a context inside the course such as the request context.
     * @param string $modname Module name, e.g. 'assign'.
     * @param string $term Activity name term (case-insensitive).
     * @param int|null $userid User, defaults to the current user.
     * @return \cm_info[] Matching course modules keyed by cm id.
     */
    public static function activity_in_course(\context $coursectx, string $modname, string $term, ?int $userid = null): array {
        $term = trim($term);
        $course = $coursectx->get_course_context(false);
        if (!$course) {
            return [];
        }
        $modinfo = get_fast_modinfo($course->instanceid, $userid ?? 0);
        if ($term === '' && $coursectx->contextlevel == CONTEXT_MODULE) {
            $cms = $modinfo->get_cms();
            $cm = $cms[$coursectx->instanceid] ?? null;
            if ($cm && $cm->modname === $modname) {
                return $cm->uservisible ? [$cm->id => $cm] : [];
            }
        }
        $result = [];
        $needle = \core_text::strtolower($term);
        foreach ($modinfo->get_instances_of($modname) as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $name = \core_text::strtolower($cm->get_formatted_name());
            if ($needle !== '' && \core_text::strpos($name, $needle) === false) {
                continue;
            }
            $result[$cm->id] = $cm;
        }
        return $result;
    }

    /**
     * Candidate rows for an ambiguous activity match.
     *
     * @param \cm_info[] $cms Course modules.
     * @return array Rows.
     */
    public static function activity_candidate_rows(array $cms): array {
        $rows = [];
        foreach ($cms as $cm) {
            $url = $cm->url;
            $rows[] = [
                'label' => $cm->get_formatted_name(),
                'value' => '',
                'url' => $url ? $url->out(false) : null,
            ];
        }
        return $rows;
    }
}
