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
 * Base class for assistant intents.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class intent_base {
    /**
     * Machine name of the intent.
     *
     * @return string
     */
    abstract public static function name(): string;

    /**
     * Fixed English description used in the router prompt.
     *
     * @return string
     */
    abstract public static function description(): string;

    /**
     * Parameters the router may extract, name => ['type' => PARAM_*, 'description' => string].
     *
     * @return array
     */
    public static function params(): array {
        return [];
    }

    /**
     * Whether the intent can be used for this request.
     *
     * @param request $r The request.
     * @return bool
     */
    abstract public function is_available(request $r): bool;

    /**
     * Handle the request.
     *
     * @param array $params Cleaned parameters.
     * @param request $r The request.
     * @return answer
     */
    abstract public function handle(array $params, request $r): answer;
}
