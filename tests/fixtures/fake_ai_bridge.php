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
 * Test double for the AI bridge.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_ai_bridge extends ai_bridge {
    /** @var string[] Prompts received. */
    public array $prompts = [];
    /** @var array Queue of responses (string or null). */
    public array $responses = [];
    /** @var bool Returned by is_available(). */
    public bool $available = true;
    /** @var bool Returned by policy_accepted(). */
    public bool $accepted = true;

    #[\Override]
    public function is_available(\context $context): bool {
        return $this->available;
    }

    #[\Override]
    public function policy_accepted(int $userid): bool {
        return $this->accepted;
    }

    #[\Override]
    public function generate(\context $context, int $userid, string $prompt): ?string {
        $this->prompts[] = $prompt;
        return array_shift($this->responses);
    }
}
