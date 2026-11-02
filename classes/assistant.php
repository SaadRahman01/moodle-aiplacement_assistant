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

use aiplacement_assistant\intent\registry;

/**
 * Orchestrates one assistant request: checks, routing, intent handling, phrasing and storage.
 *
 * Answer rows are returned to the caller only; they never enter a prompt or the stored history.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assistant {
    /** @var int Maximum message length in characters. */
    public const MAXLENGTH = 2000;

    /**
     * Build an error response.
     *
     * @param string $code Short error code.
     * @return array
     */
    private static function error(string $code): array {
        return ['success' => false, 'text' => '', 'rows' => [], 'links' => [], 'menu' => [], 'error' => $code];
    }

    /**
     * Handle a request.
     *
     * @param request $r The request.
     * @param string $pickedintent Intent name picked from the menu, or ''.
     * @return array success, text, rows, links, menu, error
     */
    public function respond(request $r, string $pickedintent = ''): array {
        $bridge = \core\di::get(ai_bridge::class);
        if (!$bridge->is_available($r->context)) {
            return self::error('notavailable');
        }
        if (!$bridge->policy_accepted($r->userid)) {
            return self::error('policynotaccepted');
        }
        $message = trim($r->message);
        if (\core_text::strlen($message) > self::MAXLENGTH) {
            return self::error('messagetoolong');
        }
        if ($message === '' && $pickedintent === '') {
            return self::error('messageempty');
        }
        if (!rate_limiter::allow($r->userid)) {
            return self::error('ratelimited');
        }

        $r = new request($r->context, $r->userid, $message, $r->pagehelp, $r->docsurl);
        $intents = \core\di::get(registry::class)->available($r);
        $conversation = new conversation($r->userid);
        $lang = current_language();

        if ($pickedintent !== '' && isset($intents[$pickedintent])) {
            $name = $pickedintent;
            $params = [];
            $usertext = get_string('intent_' . $name, 'aiplacement_assistant');
            $question = $message !== '' ? $message : $usertext;
        } else {
            if ($message === '') {
                return self::error('messageempty');
            }
            $router = new router();
            $history = $conversation->recent(6);
            $prompt = $router->build_prompt($message, $history, $intents, $lang);
            $raw = $bridge->generate($r->context, $r->userid, $prompt);
            if ($raw === null) {
                return self::error('aifailed');
            }
            $route = $router->parse($raw, $intents);
            if ($route === null) {
                $text = get_string('fallbackmenu', 'aiplacement_assistant');
                $conversation->add('user', $message, $r->context->id);
                $conversation->add('assistant', $text, $r->context->id);
                return [
                    'success' => true,
                    'text' => $text,
                    'rows' => [],
                    'links' => [],
                    'menu' => self::menu($intents),
                    'error' => '',
                ];
            }
            $name = $route['intent'];
            $params = $route['params'];
            $usertext = $message;
            $question = $message;
        }

        try {
            $answer = $intents[$name]->handle($params, $r);
        } catch (\Throwable $e) {
            debugging('Assistant intent ' . $name . ' failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return self::error('generic');
        }
        $text = $answer->text;
        if ($text === '' && $answer->facts === []) {
            // Nothing to phrase (not found, pick one, feature off): show the fixed text rather than let the AI guess.
            $text = $answer->fallback;
        }
        if ($text === '') {
            $prompt = prompt::PREAMBLE . "\n\n"
                . prompt::untrusted('facts', json_encode($answer->facts), 2000) . "\n\n"
                . prompt::untrusted('question', $question, 2000) . "\n\n"
                . "Write one or two friendly sentences in language {$lang}. "
                . 'The facts are correct and complete; use their numbers exactly and never say information is missing. '
                . 'Do not mention the facts themselves. '
                . 'Do not invent names, dates or numbers not in the facts. '
                . 'The exact results are shown to the user below your sentences; do not restate or contradict them.';
            $generated = $bridge->generate($r->context, $r->userid, $prompt);
            $generated = $generated === null ? '' : prompt::strip_markers($generated);
            $text = $generated === '' ? $answer->fallback : $generated;
        }

        $conversation->add('user', $usertext, $r->context->id, $name);
        // The shown text may contain real names restored after the AI call; history is sent to the AI again.
        $stored = $answer->historytext !== '' ? $answer->historytext : $text;
        $conversation->add('assistant', $stored, $r->context->id, $name);

        return [
            'success' => true,
            'text' => $text,
            'rows' => $answer->rows,
            'links' => $answer->links,
            'menu' => [],
            'error' => '',
        ];
    }

    /**
     * Build the menu of available intents.
     *
     * @param array $intents name => intent instance.
     * @return array List of intent/label.
     */
    private static function menu(array $intents): array {
        $menu = [];
        foreach (array_keys($intents) as $name) {
            $menu[] = ['intent' => $name, 'label' => get_string('intent_' . $name, 'aiplacement_assistant')];
        }
        return $menu;
    }
}
