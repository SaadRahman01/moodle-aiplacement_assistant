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
 * Upcoming deadlines for the current student.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class due_this_week extends intent_base {
    #[\Override]
    public static function name(): string {
        return 'due_this_week';
    }

    #[\Override]
    public static function description(): string {
        return 'Which of the user\'s own assignments, quizzes or other activities are due soon, '
            . 'e.g. "What is due this week?"';
    }

    #[\Override]
    public static function params(): array {
        return [
            'days' => ['type' => PARAM_INT, 'description' => 'Number of days to look ahead, 1 to 31 (default 7)'],
        ];
    }

    #[\Override]
    public function is_available(request $r): bool {
        return $r->userid > 0 && !isguestuser($r->userid);
    }

    #[\Override]
    public function handle(array $params, request $r): answer {
        $days = (int) ($params['days'] ?? 7);
        if ($days < 1 || $days > 31) {
            $days = 7;
        }
        $now = time();
        $user = \core_user::get_user($r->userid);
        $events = \core_calendar\local\api::get_action_events_by_timesort(
            $now,
            $now + $days * DAYSECS,
            null,
            50,
            // Skip courses where the user's enrolment is suspended, as the Timeline block does.
            true,
            $user
        );

        $rows = [];
        foreach ($events as $event) {
            $label = format_string($event->get_name(), true, ['escape' => false]);
            $course = $event->get_course();
            if ($course && $course->get('shortname')) {
                $label .= ' (' . format_string($course->get('shortname'), true, ['escape' => false]) . ')';
            }
            $timesort = $event->get_times()->get_sort_time()->getTimestamp();
            $action = $event->get_action();
            $url = $action ? $action->get_url() : new \moodle_url('/calendar/view.php', ['view' => 'upcoming']);
            $rows[] = [
                'label' => $label,
                'value' => userdate($timesort, get_string('strftimedatetime', 'langconfig')),
                'url' => $url->out(false),
            ];
        }

        $count = count($rows);
        $a = (object) ['count' => $count, 'days' => $days];
        return new answer(
            facts: ['count' => $count, 'days' => $days],
            fallback: get_string($count ? 'due_count' : 'due_none', 'aiplacement_assistant', $a),
            rows: $rows,
        );
    }
}
