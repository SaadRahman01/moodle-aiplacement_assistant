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

use aiplacement_assistant\ai_bridge;
use aiplacement_assistant\answer;
use aiplacement_assistant\prompt;
use aiplacement_assistant\request;

/**
 * General help intent: how-to questions grounded in the page help.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class general_help extends intent_base {
    #[\Override]
    public static function name(): string {
        return 'general_help';
    }

    #[\Override]
    public static function description(): string {
        return 'How-to questions about using Moodle or the current page, e.g. "How do I upload a file?" '
            . 'Use only if no other intent fits.';
    }

    #[\Override]
    public function is_available(request $r): bool {
        return true;
    }

    #[\Override]
    public function handle(array $params, request $r): answer {
        $docsurl = self::safe_docs_url($r->docsurl);
        $links = [['label' => get_string('help_docs', 'aiplacement_assistant'), 'url' => $docsurl]];
        if (trim($r->message) === '') {
            // Picked from the menu with no question: ask, rather than send the AI nothing to answer.
            return new answer(text: get_string('help_ask', 'aiplacement_assistant'), links: $links);
        }
        $lang = current_language();
        $prompt = prompt::PREAMBLE . "\n\n"
            . prompt::untrusted('pagehelp', $r->pagehelp, 8000) . "\n\n"
            . prompt::untrusted('question', $r->message, 2000) . "\n\n"
            . "Answer using the page help and well-known Moodle behaviour, in language {$lang}, "
            . 'at most 120 words. If you are not confident, reply exactly NOT_SURE.';

        $generated = \core\di::get(ai_bridge::class)->generate($r->context, $r->userid, $prompt);
        $trimmed = $generated === null ? '' : prompt::strip_markers($generated);
        // Small models sometimes write an answer and then add the sentinel, so it counts anywhere in the reply.
        if ($trimmed === '' || preg_match('/(?<![\p{L}\p{N}_])NOT_SURE(?![\p{L}\p{N}_])/u', $trimmed)) {
            $text = get_string('help_notsure', 'aiplacement_assistant');
        } else {
            $text = $trimmed;
        }

        return new answer(text: $text, links: $links);
    }

    /**
     * Keep the supplied docs URL only if it is an https docs.moodle.org URL.
     *
     * @param string $url Supplied URL.
     * @return string
     */
    private static function safe_docs_url(string $url): string {
        $parts = parse_url($url);
        if (
            $parts !== false
            && isset($parts['scheme'], $parts['host'])
            && strtolower($parts['scheme']) === 'https'
            && strtolower($parts['host']) === 'docs.moodle.org'
            && !isset($parts['user'])
            && !isset($parts['pass'])
        ) {
            return $url;
        }
        return get_docs_url('');
    }
}
