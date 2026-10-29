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
 * Students who have not submitted an assignment, for graders.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class not_submitted extends intent_base {
    #[\Override]
    public static function name(): string {
        return 'not_submitted';
    }

    #[\Override]
    public static function description(): string {
        return 'Which students have not submitted an assignment yet (for teachers), '
            . 'e.g. "Who hasn\'t submitted Essay One?"';
    }

    #[\Override]
    public static function params(): array {
        return [
            'activity' => ['type' => PARAM_TEXT, 'description' => 'Assignment name; leave empty if the user means the current assignment or did not name one'],
        ];
    }

    #[\Override]
    public function is_available(request $r): bool {
        $coursectx = $r->context->get_course_context(false);
        return $coursectx && $coursectx->instanceid != SITEID
            && has_capability('mod/assign:grade', $coursectx, $r->userid);
    }

    #[\Override]
    public function handle(array $params, request $r): answer {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $coursectx = $r->context->get_course_context(false);
        if (!$coursectx) {
            return new answer(fallback: get_string('notfound_activity', 'aiplacement_assistant'));
        }
        $cms = lookup::activity_in_course($r->context, 'assign', (string) ($params['activity'] ?? ''), $r->userid);
        $cms = array_filter(
            $cms,
            fn(\cm_info $cm) => has_capability('mod/assign:grade', \context_module::instance($cm->id), $r->userid)
        );
        if (!$cms) {
            return new answer(fallback: get_string('notfound_activity', 'aiplacement_assistant'));
        }
        if (count($cms) > 1) {
            return new answer(
                fallback: get_string('pick_activity', 'aiplacement_assistant'),
                rows: lookup::activity_candidate_rows($cms),
            );
        }

        $cm = reset($cms);
        $cmctx = \context_module::instance($cm->id);
        $assign = new \assign($cmctx, $cm, $cm->get_course());
        $name = $cm->get_formatted_name();

        $group = 0;
        if (
            groups_get_activity_groupmode($cm) == SEPARATEGROUPS
            && !has_capability('moodle/site:accessallgroups', $cmctx, $r->userid)
        ) {
            $group = (int) groups_get_activity_group($cm, true);
            if (!$group) {
                // Not in any group: nobody is visible, as in the grading table.
                return new answer(
                    facts: ['missing' => 0, 'total' => 0, 'activity' => $name],
                    fallback: get_string('not_submitted_none', 'aiplacement_assistant', $name),
                );
            }
        }

        $participants = $assign->list_participants($group, true);
        $teams = !empty($assign->get_instance()->teamsubmission);
        $missing = [];
        foreach ($participants as $participant) {
            $submission = $teams
                ? $assign->get_group_submission($participant->id, 0, false)
                : $assign->get_user_submission($participant->id, false);
            $status = $submission ? $submission->status : '';
            if ($status === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                continue;
            }
            $missing[$participant->id] = $status === ASSIGN_SUBMISSION_STATUS_DRAFT ? 'status_draft' : 'status_none';
        }

        $rows = [];
        if ($missing) {
            $fields = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
            $users = $DB->get_records_list('user', 'id', array_keys($missing), '', $fields);
            $viewfullnames = has_capability('moodle/site:viewfullnames', $cmctx, $r->userid);
            // Blind marking hides identities from graders who may not view blind details, as in the grading table.
            $blind = $assign->is_blind_marking() && !has_capability('mod/assign:viewblinddetails', $cmctx, $r->userid);
            $url = (new \moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grading']))->out(false);
            foreach ($missing as $userid => $status) {
                if (!isset($users[$userid])) {
                    continue;
                }
                $rows[] = [
                    'label' => $blind
                        ? get_string('hiddenuser', 'assign') . $assign->get_uniqueid_for_user($userid)
                        : fullname($users[$userid], $viewfullnames),
                    'value' => get_string($status, 'aiplacement_assistant'),
                    'url' => $url,
                ];
            }
            \core_collator::asort_array_of_arrays_by_key($rows, 'label', \core_collator::SORT_NATURAL);
            $rows = array_values($rows);
        }

        $count = count($rows);
        $a = (object) ['missing' => $count, 'total' => count($participants), 'activity' => $name];
        return new answer(
            facts: ['missing' => $count, 'total' => count($participants), 'activity' => $name],
            fallback: $count
                ? get_string('not_submitted_count', 'aiplacement_assistant', $a)
                : get_string('not_submitted_none', 'aiplacement_assistant', $name),
            rows: $rows,
        );
    }
}
