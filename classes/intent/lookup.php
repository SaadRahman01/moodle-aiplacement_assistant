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
    /** @var string[] Leading words that only point at the current item, e.g. "this" in "this course". */
    private const DEICTIC = ['this', 'the', 'my', 'current', 'our'];

    /** @var string[] Trailing type nouns, e.g. "forum" in "the Course Chat forum". */
    private const TYPENOUNS = ['course', 'class', 'forum', 'assignment', 'activity'];

    /**
     * Normalise a name term from the router.
     *
     * Small models often fill a name with "this course" or "the forum". Leading deictic words and one trailing
     * type noun are stripped, so such terms become empty and fall back to the current context.
     *
     * @param string $term Raw term.
     * @return string Normalised term, possibly empty.
     */
    public static function normalise_term(string $term): string {
        $words = preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY);
        while ($words && in_array(\core_text::strtolower($words[0]), self::DEICTIC, true)) {
            array_shift($words);
        }
        if ($words && in_array(\core_text::strtolower(end($words)), self::TYPENOUNS, true)) {
            array_pop($words);
        }
        return implode(' ', $words);
    }

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
     * With an empty (or deictic, such as "this course") term and a request made from inside a course,
     * that course is used.
     *
     * @param request $r The request.
     * @param string $term Course name term from the router.
     * @return array Matching course records keyed by id.
     */
    public static function resolve_courses(request $r, string $term): array {
        $term = self::normalise_term($term);
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
        $term = self::normalise_term($term);
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

    /**
     * Courses on the site (not limited to the user's enrolments) whose full or short name contains the term.
     *
     * Callers must filter the result by capability before showing it.
     *
     * @param string $term Search term (case-insensitive).
     * @param int $limit Maximum number of courses, 0 for no limit.
     * @return array Course records keyed by id.
     */
    public static function courses(string $term, int $limit = 10): array {
        global $DB;
        $like = '%' . $DB->sql_like_escape(trim($term)) . '%';
        $sql = "SELECT c.id, c.fullname, c.shortname, c.visible, c.category
                  FROM {course} c
                 WHERE c.id <> :siteid
                   AND (" . $DB->sql_like('c.shortname', ':s1', false, false)
                   . " OR " . $DB->sql_like('c.fullname', ':s2', false, false) . ")
              ORDER BY c.fullname ASC, c.id ASC";
        return $DB->get_records_sql($sql, ['siteid' => SITEID, 's1' => $like, 's2' => $like], 0, $limit);
    }

    /**
     * Users matching a term, excluding deleted users and the guest user.
     *
     * Site-wide (no course id): an exact username or email match wins, otherwise a full name match.
     * Scoped to a course: only users with any enrolment (including suspended) in it, matched by full name.
     * Identity fields (username, email) are never searched in the scoped case.
     *
     * @param string $term Search term (case-insensitive).
     * @param int $limit Maximum number of users.
     * @param int|null $courseid Restrict to users enrolled in this course.
     * @return array User records (id and name fields) keyed by id.
     */
    public static function users(string $term, int $limit = 10, ?int $courseid = null): array {
        global $CFG, $DB;
        $term = trim($term);
        if ($term === '') {
            return [];
        }
        $fields = implode(', ', array_map(fn($f) => 'u.' . $f, \core_user\fields::get_name_fields()));
        $select = "SELECT DISTINCT u.id, u.suspended, {$fields}";
        $from = " FROM {user} u";
        $params = ['guestid' => $CFG->siteguest ?? 0, 'mnet' => $CFG->mnet_localhost_id];
        if ($courseid !== null) {
            $from .= " JOIN {user_enrolments} ue ON ue.userid = u.id
                       JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid";
            $params['courseid'] = $courseid;
        }
        $base = " WHERE u.deleted = 0 AND u.id <> :guestid AND u.mnethostid = :mnet";
        $order = " ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC";

        if ($courseid === null) {
            $exact = $DB->get_records_sql(
                $select . $from . $base . " AND (" . $DB->sql_like('u.username', ':t1', false, false)
                    . " OR " . $DB->sql_like('u.email', ':t2', false, false) . ")" . $order,
                $params + ['t1' => $DB->sql_like_escape($term), 't2' => $DB->sql_like_escape($term)],
                0,
                $limit
            );
            if ($exact) {
                return $exact;
            }
        }
        $like = '%' . $DB->sql_like_escape($term) . '%';
        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        return $DB->get_records_sql(
            $select . $from . $base . " AND " . $DB->sql_like($fullname, ':name', false, false) . $order,
            $params + ['name' => $like],
            0,
            $limit
        );
    }

    /**
     * Candidate rows for an ambiguous user match.
     *
     * Only the display name is shown, never email or other identity fields.
     *
     * @param array $users User records.
     * @param int|null $courseid Course the search was scoped to, if any.
     * @return array Rows.
     */
    public static function user_candidate_rows(array $users, ?int $courseid = null): array {
        $rows = [];
        foreach ($users as $user) {
            $url = $courseid === null
                ? new \moodle_url('/user/profile.php', ['id' => $user->id])
                : new \moodle_url('/user/view.php', ['id' => $user->id, 'course' => $courseid]);
            $rows[] = ['label' => fullname($user), 'value' => '', 'url' => $url->out(false)];
        }
        return $rows;
    }
}
