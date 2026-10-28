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
 * Per-user hourly rate limiter backed by an application cache.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limiter {
    /** @var int Window length in seconds. */
    private const WINDOW = 3600;

    /**
     * Check whether the user may make a request, counting it when allowed.
     *
     * @param int $userid
     * @return bool
     */
    public static function allow(int $userid): bool {
        $limit = (int) get_config('aiplacement_assistant', 'ratelimit');
        if ($limit <= 0) {
            return true;
        }
        $cache = \cache::make('aiplacement_assistant', 'ratelimit');
        $now = time();
        $state = $cache->get($userid);
        if (!is_array($state) || !isset($state['start'], $state['count']) || $now - $state['start'] >= self::WINDOW) {
            $state = ['start' => $now, 'count' => 0];
        }
        if ($state['count'] >= $limit) {
            return false;
        }
        $state['count']++;
        $cache->set($userid, $state);
        return true;
    }
}
