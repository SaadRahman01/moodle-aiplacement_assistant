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

namespace aiplacement_assistant;

use aiplacement_assistant\intent\intent_base;

/**
 * Turns a user message into a validated intent and parameters using the AI.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class router {
    /** @var int Maximum number of history entries used. */
    private const MAXHISTORY = 6;

    /**
     * Build the routing prompt.
     *
     * @param string $message The user's message.
     * @param array $history Entries of ['role' => 'user'|'assistant', 'text' => string].
     * @param intent_base[] $intents Available intents keyed by name.
     * @param string $lang Current language code.
     * @return string
     */
    public function build_prompt(string $message, array $history, array $intents, string $lang): string {
        $lines = [];
        foreach ($intents as $name => $intent) {
            $line = $name . ': ' . $intent::description();
            $params = [];
            foreach ($intent::params() as $pname => $def) {
                $params[] = $pname . ' (' . $def['type'] . ') — ' . $def['description'];
            }
            if ($params) {
                $line .= '; params: ' . implode(', ', $params);
            }
            $lines[] = $line;
        }

        $hist = [];
        foreach (array_slice(array_values($history), -self::MAXHISTORY) as $entry) {
            $hist[] = ($entry['role'] ?? 'user') . ': ' . ($entry['text'] ?? '');
        }

        return prompt::PREAMBLE . "\n\n"
            . "Choose the intent that best matches the user's message. Available intents:\n"
            . implode("\n", $lines) . "\n\n"
            . "The user's language is {$lang}.\n\n"
            . prompt::untrusted('history', implode("\n", $hist), 6000) . "\n\n"
            . prompt::untrusted('message', $message, 2000) . "\n\n"
            . 'Reply with only JSON: {"intent": "<name>", "params": {...}}. '
            . 'Use general_help for how-to questions or anything else.';
    }

    /**
     * Parse and validate the AI reply.
     *
     * @param string $raw Raw AI output.
     * @param intent_base[] $intents Available intents keyed by name.
     * @return array|null ['intent' => string, 'params' => array] or null if invalid.
     */
    public function parse(string $raw, array $intents): ?array {
        $raw = str_replace(str_repeat(chr(96), 3), '', $raw);
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        $data = json_decode(substr($raw, $start, $end - $start + 1), true);
        $ok = is_array($data) && isset($data['intent']) && is_string($data['intent'])
            && array_key_exists($data['intent'], $intents);
        if (!$ok) {
            return null;
        }
        $name = $data['intent'];
        $declared = $intents[$name]::params();
        $given = isset($data['params']) && is_array($data['params']) ? $data['params'] : [];
        $params = [];
        foreach ($declared as $pname => $def) {
            if (!array_key_exists($pname, $given) || !is_scalar($given[$pname])) {
                continue;
            }
            $value = clean_param((string) $given[$pname], $def['type']);
            if ($value !== '') {
                $params[$pname] = $value;
            }
        }
        return ['intent' => $name, 'params' => $params];
    }

    /**
     * Route a request to an intent.
     *
     * @param request $r The request.
     * @param array $history Conversation history.
     * @param intent_base[] $intents Available intents keyed by name.
     * @return array|null ['intent' => string, 'params' => array] or null.
     */
    public function route(request $r, array $history, array $intents): ?array {
        $prompt = $this->build_prompt($r->message, $history, $intents, current_language());
        $raw = \core\di::get(ai_bridge::class)->generate($r->context, $r->userid, $prompt);
        if ($raw === null) {
            return null;
        }
        return $this->parse($raw, $intents);
    }
}
