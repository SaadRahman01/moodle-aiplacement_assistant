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
 * Tests for the intent registry.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \aiplacement_assistant\intent\registry
 */
final class registry_test extends \advanced_testcase {
    /**
     * Build a request at system context.
     *
     * @return request
     */
    private function request(): request {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        return new request(\context_system::instance(), (int) $user->id, 'hello');
    }

    /**
     * General help is always available.
     */
    public function test_general_help_always_available(): void {
        $available = \core\di::get(registry::class)->available($this->request());
        $this->assertArrayHasKey('general_help', $available);
        $this->assertInstanceOf(intent_base::class, $available['general_help']);
    }

    /**
     * Missing classes are skipped and injected classes are used.
     */
    public function test_custom_classes_and_missing_skipped(): void {
        $registry = new registry(['\\no\\such\\intent', general_help::class]);
        $this->assertSame(['general_help'], array_keys($registry->available($this->request())));
    }
}
