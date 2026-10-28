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
 * Test-only intent returning fixed facts, a fallback and a sensitive row.
 *
 * It reports the name general_help so the menu label string exists.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class echo_intent extends intent_base {
    #[\Override]
    public static function name(): string {
        return 'general_help';
    }

    #[\Override]
    public static function description(): string {
        return 'Echo test intent.';
    }

    #[\Override]
    public function is_available(request $r): bool {
        return true;
    }

    #[\Override]
    public function handle(array $params, request $r): answer {
        return new answer(
            '',
            ['count' => 2],
            'Two things.',
            [['label' => 'Row', 'value' => 'SECRETNAME', 'url' => null]],
            [['label' => 'Link', 'url' => 'https://example.com/x']]
        );
    }
}
