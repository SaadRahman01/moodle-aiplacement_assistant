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

use aiplacement_assistant\request;

/**
 * Registry of available intents.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {
    /** @var string[] Ordered list of intent classes. */
    public const INTENTS = [
        due_this_week::class,
        my_grade::class,
        unfinished_activities::class,
        not_submitted::class,
        inactive_users::class,
        forum_summary::class,
        access_check::class,
        general_help::class,
    ];

    /** @var string[] Configured intent classes. */
    private array $classes;

    /**
     * Constructor.
     *
     * @param string[]|null $classes Intent classes, or null for the default list.
     */
    public function __construct(?array $classes = null) {
        $this->classes = $classes ?? self::INTENTS;
    }

    /**
     * Intents available for the request, keyed by name.
     *
     * @param request $r The request.
     * @return intent_base[]
     */
    public function available(request $r): array {
        $result = [];
        foreach ($this->classes as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $intent = new $class();
            if ($intent->is_available($r)) {
                $result[$class::name()] = $intent;
            }
        }
        return $result;
    }
}
