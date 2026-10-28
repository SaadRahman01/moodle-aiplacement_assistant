<?php
// phpcs:ignoreFile -- two conditional declarations of one class (see below); Moodle sniffs expect one class per file.
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

// Core declares get_action_list() as an instance method in 4.5 and as abstract static in 5.x, and a subclass must
// match, so the class is declared conditionally.
if ((new \ReflectionMethod(\core_ai\placement::class, 'get_action_list'))->isStatic()) {
    /**
     * AI placement for the in-Moodle chat assistant (Moodle 5.x, static signature).
     *
     * @package    aiplacement_assistant
     * @copyright  2026 Saad Rahman
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    class placement extends \core_ai\placement {
        use placement_actions;

        #[\Override]
        public static function get_action_list(): array {
            return self::actions();
        }
    }
} else {
    /**
     * AI placement for the in-Moodle chat assistant (Moodle 4.5, instance signature).
     *
     * @package    aiplacement_assistant
     * @copyright  2026 Saad Rahman
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    class placement extends \core_ai\placement {
        use placement_actions;

        #[\Override]
        public function get_action_list(): array {
            return self::actions();
        }
    }
}

