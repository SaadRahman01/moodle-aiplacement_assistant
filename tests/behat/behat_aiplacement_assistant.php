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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Behat steps for aiplacement_assistant.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_aiplacement_assistant extends behat_base {
    /**
     * Configure an (unreachable) OpenAI provider so that text generation is available.
     *
     * Moodle 4.5 configures providers as plugins; Moodle 5.0+ uses provider instances.
     *
     * @Given /^a test AI provider is configured for the Course Assistant$/
     */
    public function a_test_ai_provider_is_configured_for_the_moodle_assistant(): void {
        $manager = \core\di::get(\core_ai\manager::class);
        if (method_exists($manager, 'create_provider_instance')) {
            $manager->create_provider_instance(
                classname: \aiprovider_openai\provider::class,
                name: 'Assistant test provider',
                enabled: true,
                config: ['apikey' => '123', 'orgid' => 'abc'],
            );
        } else {
            \core\plugininfo\aiprovider::enable_plugin('openai', 1);
            set_config('apikey', '123', 'aiprovider_openai');
        }
        \core\plugininfo\aiplacement::enable_plugin('assistant', 1);
    }
}
