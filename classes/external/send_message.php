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

use aiplacement_assistant\assistant;
use aiplacement_assistant\request;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service sending a message to the assistant.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context the assistant is used in'),
            'message' => new external_value(PARAM_RAW, 'The user message'),
            'intent' => new external_value(PARAM_ALPHANUMEXT, 'Intent picked from the menu', VALUE_DEFAULT, ''),
            'pagehelp' => new external_value(PARAM_RAW, 'Help text for the current page', VALUE_DEFAULT, ''),
            'docsurl' => new external_value(PARAM_URL, 'Documentation URL for the current page', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Send a message.
     *
     * @param int $contextid Context id.
     * @param string $message Message.
     * @param string $intent Picked intent.
     * @param string $pagehelp Page help text.
     * @param string $docsurl Docs URL.
     * @return array
     */
    public static function execute(
        int $contextid,
        string $message,
        string $intent = '',
        string $pagehelp = '',
        string $docsurl = ''
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'message' => $message,
            'intent' => $intent,
            'pagehelp' => $pagehelp,
            'docsurl' => $docsurl,
        ]);
        $context = \core\context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('aiplacement/assistant:use', $context);

        $request = new request($context, (int) $USER->id, $params['message'], $params['pagehelp'], $params['docsurl']);
        $result = (new assistant())->respond($request, $params['intent']);

        $result['text'] = \core_external\util::format_text($result['text'], FORMAT_PLAIN, $contextid)[0];
        $result['rows'] = array_map(function (array $row): array {
            $out = ['label' => $row['label'], 'value' => $row['value']];
            if (!empty($row['url'])) {
                $out['url'] = $row['url'];
            }
            return $out;
        }, $result['rows']);
        return $result;
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request succeeded'),
            'text' => new external_value(PARAM_RAW, 'Reply text'),
            'rows' => new external_multiple_structure(new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'Row label'),
                'value' => new external_value(PARAM_TEXT, 'Row value'),
                'url' => new external_value(PARAM_URL, 'Row link', VALUE_OPTIONAL),
            ])),
            'links' => new external_multiple_structure(new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'Link label'),
                'url' => new external_value(PARAM_URL, 'Link URL'),
            ])),
            'menu' => new external_multiple_structure(new external_single_structure([
                'intent' => new external_value(PARAM_ALPHANUMEXT, 'Intent name'),
                'label' => new external_value(PARAM_TEXT, 'Menu label'),
            ])),
            'error' => new external_value(PARAM_ALPHANUMEXT, 'Error code, empty on success'),
        ]);
    }
}
