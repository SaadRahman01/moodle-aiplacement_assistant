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

use core_ai\aiactions\generate_text;

/**
 * Compatibility layer over the core AI subsystem (Moodle 4.5 to 5.x).
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_bridge {
    /**
     * Call a core_ai\manager method that is static in 4.5 and an instance method in 5.0+.
     *
     * @param string $method Method name.
     * @param mixed ...$args Arguments.
     * @return mixed
     */
    private function call_manager(string $method, ...$args) {
        if ((new \ReflectionMethod(\core_ai\manager::class, $method))->isStatic()) {
            return \core_ai\manager::$method(...$args);
        }
        return \core\di::get(\core_ai\manager::class)->$method(...$args);
    }

    /**
     * Whether the assistant can be used in the given context.
     *
     * @param \context $context The context.
     * @return bool
     */
    public function is_available(\context $context): bool {
        $plugininfo = \core_plugin_manager::resolve_plugininfo_class('aiplacement');
        if (!$plugininfo::is_plugin_enabled('assistant')) {
            return false;
        }
        if (!has_capability('aiplacement/assistant:use', $context)) {
            return false;
        }
        $action = generate_text::class;
        if (
            !$this->call_manager('is_action_available', $action)
            || !$this->call_manager('is_action_enabled', 'aiplacement_assistant', $action)
        ) {
            return false;
        }
        $providers = $this->call_manager('get_providers_for_actions', [$action], true);
        return !empty($providers[$action]);
    }

    /**
     * Whether the user has accepted the AI policy.
     *
     * @param int $userid User id.
     * @return bool
     */
    public function policy_accepted(int $userid): bool {
        return (bool) $this->call_manager('get_user_policy_status', $userid);
    }

    /**
     * Generate text from a prompt.
     *
     * @param \context $context The context.
     * @param int $userid User id.
     * @param string $prompt The prompt.
     * @return string|null Generated text, or null on any failure.
     */
    public function generate(\context $context, int $userid, string $prompt): ?string {
        try {
            $action = new generate_text(contextid: $context->id, userid: $userid, prompttext: $prompt);
            $response = \core\di::get(\core_ai\manager::class)->process_action($action);
            if (!$response->get_success()) {
                return null;
            }
            $data = $response->get_response_data();
            return isset($data['generatedcontent']) ? (string) $data['generatedcontent'] : null;
        } catch (\Throwable $e) {
            debugging('aiplacement_assistant: AI request failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }
}
