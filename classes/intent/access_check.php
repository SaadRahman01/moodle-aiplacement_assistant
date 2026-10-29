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
 * Explain why a user cannot see or access a course (for administrators and enrolment managers).
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_check extends intent_base {
    /** @var int Maximum candidates listed for an ambiguous name. */
    private const MAX_CANDIDATES = 10;

    #[\Override]
    public static function name(): string {
        return 'access_check';
    }

    #[\Override]
    public static function description(): string {
        return 'Diagnose why another, named user cannot see or access a course (for administrators and enrolment managers), '
            . 'e.g. "Why can\'t Ali see Biology 101?" or "Sue can\'t access this course"';
    }

    #[\Override]
    public static function params(): array {
        return [
            'user' => ['type' => PARAM_TEXT, 'description' => 'Name, username or email of the user being checked'],
            'course' => [
                'type' => PARAM_TEXT,
                'description' => 'Course name or short name; leave empty if the user means the current course',
            ],
        ];
    }

    #[\Override]
    public function is_available(request $r): bool {
        return has_capability('moodle/course:enrolreview', $r->context, $r->userid)
            || has_capability('moodle/site:config', \context_system::instance(), $r->userid);
    }

    #[\Override]
    public function handle(array $params, request $r): answer {
        // Resolve the course first, so that nothing is revealed about users unless the course is one we may inspect.
        $courses = $this->resolve_courses($r, lookup::normalise_term((string) ($params['course'] ?? '')));
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
        $coursectx = \context_course::instance($course->id);

        // Site-wide user search only for those who could search all users in the enrolment dialog.
        $sitewide = has_capability('moodle/site:config', \context_system::instance(), $r->userid)
            || has_capability('enrol/manual:enrol', $coursectx, $r->userid);
        $scope = $sitewide ? null : (int) $course->id;
        $users = lookup::users(trim((string) ($params['user'] ?? '')), self::MAX_CANDIDATES + 1, $scope);
        if (!$users) {
            return new answer(fallback: get_string('notfound_user', 'aiplacement_assistant'));
        }
        if (count($users) > 1) {
            return new answer(
                fallback: get_string('pick_user', 'aiplacement_assistant'),
                rows: lookup::user_candidate_rows(array_slice($users, 0, self::MAX_CANDIDATES, true), $scope),
            );
        }
        return $this->diagnose(reset($users), $course, $coursectx);
    }

    /**
     * Courses matching the router's course term that the requester may inspect.
     *
     * @param request $r The request.
     * @param string $term Course term.
     * @return array Course records keyed by id.
     */
    private function resolve_courses(request $r, string $term): array {
        if ($term === '') {
            $ctx = $r->context->get_course_context(false);
            if (!$ctx || $ctx->instanceid == SITEID) {
                return [];
            }
            $found = [$ctx->instanceid => get_course($ctx->instanceid)];
        } else {
            $found = lookup::courses($term, 200);
        }
        $result = [];
        foreach ($found as $id => $course) {
            if (has_capability('moodle/course:enrolreview', \context_course::instance($course->id), $r->userid)) {
                $result[$id] = $course;
            }
        }
        // A short name is unique, so an exact match is not ambiguous.
        foreach ($result as $id => $course) {
            if ($term !== '' && \core_text::strtolower($course->shortname) === \core_text::strtolower($term)) {
                return [$id => $course];
            }
        }
        return array_slice($result, 0, self::MAX_CANDIDATES, true);
    }

    /**
     * Run the access checks for one user in one course.
     *
     * @param \stdClass $user The user (id, suspended, name fields).
     * @param \stdClass $course The course.
     * @param \context_course $coursectx Course context.
     * @return answer
     */
    private function diagnose(\stdClass $user, \stdClass $course, \context_course $coursectx): answer {
        global $DB;
        $now = time();
        $checks = [];

        $checks['user_suspended'] = [empty($user->suspended), ''];

        $enrolments = $DB->get_records_sql(
            "SELECT ue.id, ue.status, ue.timestart, ue.timeend, e.status AS enrolstatus
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid AND e.courseid = :courseid",
            ['userid' => $user->id, 'courseid' => $course->id]
        );
        $checks['enrolled'] = [(bool) $enrolments, ''];
        if ($enrolments) {
            $enabled = array_filter($enrolments, fn($ue) => $ue->enrolstatus == ENROL_INSTANCE_ENABLED);
            $checks['enrol_instance_enabled'] = [(bool) $enabled, ''];
            // An enrolment is usable only when the same row passes every condition (as is_enrolled() onlyactive).
            $usable = array_filter($enrolments, fn($ue) => $ue->enrolstatus == ENROL_INSTANCE_ENABLED
                && $ue->status == ENROL_USER_ACTIVE
                && $ue->timestart <= $now && ($ue->timeend == 0 || $ue->timeend > $now));
            $detail = '';
            if (!$usable) {
                // Describe the enabled-instance row closest to usable, if any.
                $candidates = $enabled ?: $enrolments;
                $ue = reset($candidates);
                foreach ($candidates as $candidate) {
                    if ($candidate->status == ENROL_USER_ACTIVE) {
                        $ue = $candidate;
                        break;
                    }
                }
                if ($ue->enrolstatus != ENROL_INSTANCE_ENABLED) {
                    $detail = get_string('check_detail_instancedisabled', 'aiplacement_assistant');
                } else if ($ue->status != ENROL_USER_ACTIVE) {
                    $detail = get_string('check_detail_suspended', 'aiplacement_assistant');
                } else if ($ue->timestart > $now) {
                    $detail = get_string('check_detail_notstarted', 'aiplacement_assistant');
                } else {
                    $detail = get_string('check_detail_expired', 'aiplacement_assistant');
                }
            }
            $checks['enrolment_active'] = [(bool) $usable, $detail];
        }

        $viewhidden = has_capability('moodle/course:viewhiddencourses', $coursectx, $user->id);
        $checks['course_visible'] = [!empty($course->visible) || $viewhidden, ''];

        // Every ancestor category counts: a visible subcategory of a hidden category is still out of reach.
        $catok = true;
        $path = $DB->get_field('course_categories', 'path', ['id' => $course->category]);
        $ids = $path ? array_filter(array_map('intval', explode('/', $path))) : [];
        if ($ids) {
            $categories = $DB->get_records_list('course_categories', 'id', $ids, '', 'id, visible');
            foreach ($categories as $category) {
                if (
                    !$category->visible
                    && !has_capability('moodle/category:viewhiddencategories', \context_coursecat::instance($category->id), $user->id)
                ) {
                    $catok = false;
                    break;
                }
            }
        }
        $checks['category_visible'] = [$catok, ''];

        $checks['has_role'] = [(bool) get_user_roles($coursectx, $user->id, true), ''];

        $rows = [];
        $problems = 0;
        foreach ($checks as $key => [$ok, $detail]) {
            if (!$ok) {
                $problems++;
            }
            $value = get_string($ok ? 'check_ok' : 'check_problem', 'aiplacement_assistant');
            if ($detail !== '') {
                $value .= ': ' . $detail;
            }
            $rows[] = ['label' => get_string('check_' . $key, 'aiplacement_assistant'), 'value' => $value, 'url' => null];
        }

        $a = (object) [
            'problems' => $problems,
            'user' => fullname($user),
            'course' => format_string($course->fullname, true, ['context' => $coursectx]),
        ];
        return new answer(
            facts: ['problems' => $problems],
            fallback: get_string($problems ? 'check_summary_problems' : 'check_summary_ok', 'aiplacement_assistant', $a),
            rows: $rows,
            links: [
                [
                    'label' => get_string('participants'),
                    'url' => (new \moodle_url('/user/index.php', ['id' => $course->id]))->out(false),
                ],
                [
                    'label' => get_string('enrolmentinstances', 'enrol'),
                    'url' => (new \moodle_url('/enrol/instances.php', ['id' => $course->id]))->out(false),
                ],
            ],
            // The fallback names the checked user; history is sent to the AI again, so store a version without it.
            historytext: get_string('check_history', 'aiplacement_assistant', $a),
        );
    }
}
