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
 * Tests for the admin settings.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversNothing
 */
final class settings_test extends \advanced_testcase {
    /**
     * Rate limit and retention accept only whole numbers of zero or more.
     *
     * @dataProvider values_provider
     * @param string $name Setting name.
     * @param string $value Submitted value.
     * @param bool $valid Whether the value is accepted.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('values_provider')]
    public function test_values(string $name, string $value, bool $valid): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $page = admin_get_root(true, true)->locate('aiplacement_assistant');
        $this->assertNotNull($page);
        $found = array_filter((array) $page->settings, fn($s) => $s->name === $name);
        $this->assertCount(1, $found);
        $setting = reset($found);
        $this->assertSame($valid, $setting->write_setting($value) === '', "{$name}={$value}");
    }

    /**
     * Setting values.
     *
     * @return array
     */
    public static function values_provider(): array {
        return [
            ['ratelimit', '0', true],
            ['ratelimit', '30', true],
            ['ratelimit', '-1', false],
            ['ratelimit', 'abc', false],
            ['retentiondays', '0', true],
            ['retentiondays', '365', true],
            ['retentiondays', '-5', false],
        ];
    }
}
