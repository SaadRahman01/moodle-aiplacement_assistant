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
 * Immutable description of one assistant request.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class request {
    /**
     * Constructor.
     *
     * @param \context $context Context the request was made from.
     * @param int $userid User id.
     * @param string $message The user's message.
     * @param string $pagehelp Help text for the current page.
     * @param string $docsurl Documentation URL for the current page.
     */
    public function __construct(
        /** @var \context Context the request was made from. */
        public readonly \context $context,
        /** @var int User id. */
        public readonly int $userid,
        /** @var string The user's message. */
        public readonly string $message,
        /** @var string Help text for the current page. */
        public readonly string $pagehelp = '',
        /** @var string Documentation URL for the current page. */
        public readonly string $docsurl = '',
    ) {
    }
}
