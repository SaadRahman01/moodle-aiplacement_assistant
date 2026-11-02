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
 * Tests for the rate limiter.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \aiplacement_assistant\rate_limiter
 */
final class rate_limiter_test extends \advanced_testcase {
    /**
     * The 31st request in an hour is blocked.
     */
    public function test_rate_limit_blocks_31st(): void {
        $this->resetAfterTest();
        set_config('ratelimit', 30, 'aiplacement_assistant');
        for ($i = 1; $i <= 30; $i++) {
            $this->assertTrue(rate_limiter::allow(7), "call $i");
        }
        $this->assertFalse(rate_limiter::allow(7));
        $this->assertTrue(rate_limiter::allow(8));
    }

    /**
     * An expired window resets the count.
     */
    public function test_rate_limit_window_resets(): void {
        $this->resetAfterTest();
        set_config('ratelimit', 2, 'aiplacement_assistant');
        $this->assertTrue(rate_limiter::allow(9));
        $this->assertTrue(rate_limiter::allow(9));
        $this->assertFalse(rate_limiter::allow(9));
        $cache = \cache::make('aiplacement_assistant', 'ratelimit');
        $cache->set(9, ['start' => time() - 3601, 'count' => 2]);
        $this->assertTrue(rate_limiter::allow(9));
    }

    /**
     * A limit of zero or less means unlimited.
     */
    public function test_zero_is_unlimited(): void {
        $this->resetAfterTest();
        set_config('ratelimit', 0, 'aiplacement_assistant');
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue(rate_limiter::allow(11));
        }
    }
}
