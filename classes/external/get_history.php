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

namespace aiplacement_assistant\external;

use aiplacement_assistant\conversation;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service returning the user's assistant conversation.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_history extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context the assistant is used in'),
        ]);
    }

    /**
     * Get the history.
     *
     * @param int $contextid Context id.
     * @return array
     */
    public static function execute(int $contextid): array {
        global $USER;
        ['contextid' => $contextid] = self::validate_parameters(self::execute_parameters(), ['contextid' => $contextid]);
        $context = \core\context::instance_by_id($contextid);
        self::validate_context($context);
        require_capability('aiplacement/assistant:use', $context);

        $messages = [];
        foreach ((new conversation((int) $USER->id))->history() as $m) {
            $messages[] = [
                'role' => $m['role'],
                'text' => \core_external\util::format_text($m['text'], FORMAT_PLAIN, $contextid)[0],
            ];
        }
        return ['messages' => $messages];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'messages' => new external_multiple_structure(new external_single_structure([
                'role' => new external_value(PARAM_ALPHA, 'user or assistant'),
                'text' => new external_value(PARAM_RAW, 'Message text'),
            ])),
        ]);
    }
}
