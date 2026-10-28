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

namespace aiplacement_assistant\output;

use aiplacement_assistant\ai_bridge;
use core\hook\output\before_footer_html_generation;

/**
 * Loads the assistant chat button and panel on pages where it may be used.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chat_ui {
    /** @var string[] Page layouts where the assistant is never shown. */
    private const HIDDEN_LAYOUTS = ['secure', 'embedded', 'maintenance', 'print', 'redirect', 'popup'];

    /** @var string[] Page type prefixes where the assistant is never shown (exams in progress). */
    private const HIDDEN_PAGETYPE_PREFIXES = ['mod-quiz-attempt', 'mod-quiz-summary'];

    /**
     * Whether the assistant should be shown on a page.
     *
     * @param \moodle_page $page The page.
     * @return bool
     */
    public static function should_show(\moodle_page $page): bool {
        if (during_initial_install() || !get_config('aiplacement_assistant', 'version')) {
            return false;
        }
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        if (in_array($page->pagelayout, self::HIDDEN_LAYOUTS, true)) {
            return false;
        }
        foreach (self::HIDDEN_PAGETYPE_PREFIXES as $prefix) {
            if (str_starts_with((string) $page->pagetype, $prefix)) {
                return false;
            }
        }
        return \core\di::get(ai_bridge::class)->is_available($page->context);
    }

    /**
     * Add the button, panel and JavaScript to the page.
     *
     * @param before_footer_html_generation $hook The hook.
     */
    public static function load(before_footer_html_generation $hook): void {
        global $PAGE, $USER;
        if (!self::should_show($PAGE)) {
            return;
        }
        // An empty path gives the documentation home page.
        $docsurl = get_docs_url(page_get_doc_link_path($PAGE));
        $hook->add_html($hook->renderer->render_from_template('aiplacement_assistant/panel', [
            'contextid' => $PAGE->context->id,
        ]));
        $PAGE->requires->js_call_amd('aiplacement_assistant/chat', 'init', [
            (int) $PAGE->context->id,
            $docsurl,
            (int) $USER->id,
        ]);
    }
}
