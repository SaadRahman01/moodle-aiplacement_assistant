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

/**
 * The result of handling an intent.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class answer {
    /**
     * Constructor.
     *
     * @param string $text Final answer text.
     * @param array $facts Non-personal facts (string => int|string) for the AI to phrase.
     * @param string $fallback Text to show if the AI is unavailable.
     * @param array $rows List of ['label' => string, 'value' => string, 'url' => ?string].
     * @param array $links List of ['label' => string, 'url' => string].
     * @param string $historytext Text stored in the conversation history instead of the shown text, when set.
     *                            Must never contain other people's real names, as history is sent to the AI.
     */
    public function __construct(
        /** @var string Final answer text. */
        public string $text = '',
        /** @var array Non-personal facts. */
        public array $facts = [],
        /** @var string Text shown when the AI is unavailable. */
        public string $fallback = '',
        /** @var array Result rows. */
        public array $rows = [],
        /** @var array Result links. */
        public array $links = [],
        /** @var string Text stored in the conversation history instead of the shown text, when set. */
        public string $historytext = '',
    ) {
    }
}
