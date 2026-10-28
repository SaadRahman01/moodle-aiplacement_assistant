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
 * General help intent (stub, completed in a later task).
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
        return 'General questions about using Moodle or the current page.';
    }

    #[\Override]
    public function is_available(request $r): bool {
        return true;
    }

    #[\Override]
    public function handle(array $params, request $r): answer {
        return new answer();
    }
}
