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

use aiplacement_assistant\ai_bridge;
use aiplacement_assistant\answer;
use aiplacement_assistant\prompt;
use aiplacement_assistant\request;
use mod_forum\local\container;
use mod_forum\local\vaults\discussion_list;

/**
 * AI summary of the latest posts in a forum, with authors anonymised for the AI.
 *
 * Posts are loaded through mod_forum's own vaults and capability manager, so group mode,
 * private replies, timed posts and Q and A forums are respected as in the forum UI.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forum_summary extends intent_base {
    /** @var int Maximum number of posts summarised. */
    private const MAXPOSTS = 50;

    /** @var int Maximum characters of forum text sent to the AI. */
    private const MAXCHARS = 12000;

    /** @var string Replacement for the names of people who are not post authors. */
    private const PARTICIPANT = 'a participant';

    /** @var int Number of name alternatives compiled into one regex. */
    private const REGEXCHUNK = 400;

    #[\Override]
    public static function name(): string {
        return 'forum_summary';
    }

    #[\Override]
    public static function description(): string {
        return 'Summarise the recent discussion in a forum.';
    }

    #[\Override]
    public static function params(): array {
        return [
            'forum' => ['type' => PARAM_TEXT, 'description' => 'Forum name (optional)'],
        ];
    }

    #[\Override]
    public function is_available(request $r): bool {
        $coursectx = $r->context->get_course_context(false);
        return $coursectx && $coursectx->instanceid != SITEID
            && has_capability('mod/forum:viewdiscussion', $coursectx, $r->userid);
    }

    #[\Override]
    public function handle(array $params, request $r): answer {
        global $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $cms = lookup::activity_in_course($r->context, 'forum', (string) ($params['forum'] ?? ''), $r->userid);
        $cms = array_filter(
            $cms,
            fn(\cm_info $cm) => has_capability('mod/forum:viewdiscussion', \context_module::instance($cm->id), $r->userid)
        );
        if (!$cms) {
            return new answer(fallback: get_string('notfound_activity', 'aiplacement_assistant'));
        }
        if (count($cms) > 1) {
            return new answer(
                fallback: get_string('pick_activity', 'aiplacement_assistant'),
                rows: lookup::activity_candidate_rows($cms),
            );
        }

        $cm = reset($cms);
        $name = $cm->get_formatted_name();
        $links = [['label' => $name, 'url' => (new \moodle_url('/mod/forum/view.php', ['id' => $cm->id]))->out(false)]];
        $user = \core_user::get_user($r->userid, '*', MUST_EXIST);
        $posts = $this->visible_posts($cm, $user);
        if (!$posts) {
            return new answer(
                facts: ['posts' => 0, 'forum' => $name],
                fallback: get_string('forum_empty', 'aiplacement_assistant', $name),
                links: $links,
            );
        }

        [$text, $realnames] = $this->anonymise($posts, \context_module::instance($cm->id), $r->userid);
        $lang = current_language();
        $prompt = prompt::PREAMBLE . "\n\n"
            . prompt::untrusted('forum', $text, self::MAXCHARS) . "\n\n"
            . "Summarise the forum posts above in exactly 5 short bullet points, in language {$lang}. "
            . 'Refer to people only by their labels, such as Person 1. Never guess or invent real names.';
        $reply = \core\di::get(ai_bridge::class)->generate($r->context, $r->userid, $prompt);
        if ($reply === null || trim($reply) === '') {
            // Text is set so the orchestrator shows it as is, without a phrasing call.
            $failed = get_string('summary_failed', 'aiplacement_assistant');
            return new answer(text: $failed, fallback: $failed, links: $links);
        }

        return new answer(
            text: self::restore_names(trim($reply), $realnames),
            fallback: get_string('summary_failed', 'aiplacement_assistant'),
            links: $links,
        );
    }

    /**
     * The latest posts in the forum that the user can see, oldest first, grouped by discussion.
     *
     * @param \cm_info $cm Forum course module.
     * @param \stdClass $user The user.
     * @return array List of ['subject' => string, 'post' => post entity].
     */
    private function visible_posts(\cm_info $cm, \stdClass $user): array {
        $forum = container::get_vault_factory()->get_forum_vault()->get_from_course_module_id($cm->id);
        if (!$forum) {
            return [];
        }
        $capabilitymanager = container::get_manager_factory()->get_capability_manager($forum);
        if (!$capabilitymanager->can_view_discussions($user)) {
            return [];
        }

        // Same query as the forum's discussion list; a null group means every group the user may see.
        $summaries = mod_forum_get_discussion_summaries(
            $forum,
            $user,
            null,
            discussion_list::SORTORDER_LASTPOST_DESC,
            0,
            self::MAXPOSTS
        );
        $discussions = [];
        foreach ($summaries as $summary) {
            $discussion = $summary->get_discussion();
            $discussions[$discussion->get_id()] = $discussion;
        }
        if (!$discussions) {
            return [];
        }

        $postvault = container::get_vault_factory()->get_post_vault();
        $all = $postvault->get_from_discussion_ids(
            $user,
            array_keys($discussions),
            $capabilitymanager->can_view_any_private_reply($user),
            'created DESC'
        );
        usort($all, fn($a, $b) => [$b->get_time_created(), $b->get_id()] <=> [$a->get_time_created(), $a->get_id()]);

        $posts = [];
        foreach ($all as $post) {
            $discussion = $discussions[$post->get_discussion_id()] ?? null;
            if (!$discussion || $post->is_deleted() || !$capabilitymanager->can_view_post($user, $discussion, $post)) {
                continue;
            }
            $posts[] = ['discussion' => $discussion, 'post' => $post];
            if (count($posts) >= self::MAXPOSTS) {
                break;
            }
        }
        $order = array_flip(array_keys($discussions));
        usort($posts, fn($a, $b) => [$order[$a['discussion']->get_id()], $a['post']->get_time_created(), $a['post']->get_id()]
            <=> [$order[$b['discussion']->get_id()], $b['post']->get_time_created(), $b['post']->get_id()]);
        return $posts;
    }

    /**
     * Build anonymised plain text for the posts.
     *
     * Email addresses become [email]. Authors become Person 1..n in order of appearance; the full names of
     * authors, of every actively enrolled user and of the requester are replaced in the text (authors by their
     * label, others by "a participant"); single first or last names are replaced only for authors.
     *
     * @param array $posts From visible_posts().
     * @param \context $context Forum context.
     * @param int $userid Requesting user.
     * @return array [string text, array label number => real name]
     */
    private function anonymise(array $posts, \context $context, int $userid): array {
        global $DB;
        $labels = [];
        foreach ($posts as $item) {
            $authorid = $item['post']->get_author_id();
            if (!isset($labels[$authorid])) {
                $labels[$authorid] = count($labels) + 1;
            }
        }

        $namefields = \core_user\fields::get_name_fields();
        $people = get_enrolled_users(
            $context->get_course_context(),
            '',
            0,
            'u.id, ' . implode(', ', array_map(fn($f) => 'u.' . $f, $namefields)),
            null,
            0,
            0,
            true
        );
        $extra = array_diff(array_merge(array_keys($labels), [$userid]), array_keys($people));
        if ($extra) {
            $people += $DB->get_records_list('user', 'id', $extra, '', 'id, ' . implode(', ', $namefields));
        }
        $viewfullnames = has_capability('moodle/site:viewfullnames', $context, $userid);

        $participant = self::PARTICIPANT;
        $map = [];
        foreach ($people as $person) {
            foreach (self::full_name_forms($person) as $form) {
                $map[$form] = $participant;
            }
        }
        $partowners = [];
        foreach (array_keys($labels) as $authorid) {
            if (!isset($people[$authorid])) {
                continue;
            }
            foreach ([$people[$authorid]->firstname, $people[$authorid]->lastname] as $part) {
                $part = self::normalise((string) $part);
                if (self::usable_key($part) && $part !== 'person') {
                    $partowners[$part][$authorid] = true;
                }
            }
        }
        foreach ($partowners as $part => $owners) {
            $map[$part] = count($owners) > 1 ? $participant : 'Person ' . $labels[array_key_first($owners)];
        }
        $realnames = [];
        foreach ($labels as $authorid => $number) {
            if (!isset($people[$authorid])) {
                $realnames[$number] = get_string('unknownuser');
                continue;
            }
            $realnames[$number] = fullname($people[$authorid], $viewfullnames);
            foreach (self::full_name_forms($people[$authorid]) as $form) {
                $map[$form] = 'Person ' . $number;
            }
        }
        $regexes = self::compile($map);

        $lines = [];
        $current = null;
        foreach ($posts as $item) {
            $discussion = $item['discussion'];
            $post = $item['post'];
            if ($current !== $discussion->get_id()) {
                $current = $discussion->get_id();
                $lines[] = '';
                $lines[] = 'Discussion: ' . self::scrub(self::plain($discussion->get_name()), $regexes, $map);
            }
            $lines[] = 'Person ' . $labels[$post->get_author_id()] . ': '
                . self::scrub(self::plain($post->get_message()), $regexes, $map);
        }
        return [trim(implode("\n", $lines)), $realnames];
    }

    /**
     * Convert HTML to a single line of plain text.
     *
     * @param string $html HTML.
     * @return string
     */
    private static function plain(string $html): string {
        $text = strip_tags(preg_replace('/<[^>]*>/', ' ', $html));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Lower-cased full-name spellings of a user.
     *
     * @param \stdClass $user User record with name fields.
     * @return string[]
     */
    private static function full_name_forms(\stdClass $user): array {
        $first = trim((string) $user->firstname);
        $last = trim((string) $user->lastname);
        $middle = trim((string) ($user->middlename ?? ''));
        $alt = trim((string) ($user->alternatename ?? ''));
        $forms = [fullname($user), fullname($user, true), "{$first} {$last}", "{$last} {$first}"];
        if ($middle !== '') {
            $forms[] = "{$first} {$middle} {$last}";
            $forms[] = "{$last} {$first} {$middle}";
        }
        if ($alt !== '') {
            $forms[] = "{$alt} {$last}";
            $forms[] = "{$last} {$alt}";
        }
        $result = [];
        foreach ($forms as $form) {
            $form = self::normalise($form);
            if (self::usable_key($form)) {
                $result[$form] = $form;
            }
        }
        return array_values($result);
    }

    /**
     * Lower-case and collapse whitespace, as matched against plain text.
     *
     * @param string $text Text.
     * @return string
     */
    private static function normalise(string $text): string {
        return \core_text::strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
    }

    /**
     * Whether a name key is worth matching: at least two characters and not just digits.
     *
     * @param string $key Normalised key.
     * @return bool
     */
    private static function usable_key(string $key): bool {
        return \core_text::strlen($key) >= 2 && !preg_match('/^[\d\s]+$/u', $key);
    }

    /**
     * Compile name keys into a few alternation regexes, longest keys first.
     *
     * @param array $map Normalised name => replacement.
     * @return string[] Regexes.
     */
    private static function compile(array $map): array {
        $keys = array_keys($map);
        usort($keys, fn($a, $b) => strlen($b) <=> strlen($a));
        $regexes = [];
        foreach (array_chunk($keys, self::REGEXCHUNK) as $chunk) {
            $alternatives = implode('|', array_map(fn($k) => preg_quote((string) $k, '/'), $chunk));
            $regexes[] = '/(?<![\p{L}\p{N}])(?:' . $alternatives . ')(?![\p{L}\p{N}])/iu';
        }
        return $regexes;
    }

    /**
     * Remove email addresses and replace known names in text.
     *
     * Matches are swapped for control-character tokens first so a replacement is never matched again.
     *
     * @param string $text Plain text.
     * @param string[] $regexes From compile().
     * @param array $map Normalised name => replacement.
     * @return string
     */
    private static function scrub(string $text, array $regexes, array $map): string {
        $text = str_replace(["\x01", "\x02"], '', $text);
        $text = preg_replace('/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}\-]+(?:\.[\p{L}\p{N}\-]+)+/u', '[email]', $text) ?? '';
        $found = [];
        foreach ($regexes as $regex) {
            $text = preg_replace_callback($regex, function (array $m) use (&$found, $map) {
                $found[] = $map[self::normalise($m[0])] ?? self::PARTICIPANT;
                return "\x01" . (count($found) - 1) . "\x02";
            }, $text) ?? '';
        }
        return preg_replace_callback('/\x01(\d+)\x02/', fn(array $m) => $found[(int) $m[1]], $text) ?? '';
    }

    /**
     * Put real names back in place of the Person labels this request created.
     *
     * Each "Person N" is matched as a whole token, so Person 1 never alters Person 10; unknown numbers are left as is.
     *
     * @param string $text AI reply.
     * @param array $realnames label number => real name.
     * @return string
     */
    private static function restore_names(string $text, array $realnames): string {
        return preg_replace_callback(
            '/\bPerson (\d+)\b/',
            fn(array $m) => $realnames[$m[1]] ?? $m[0],
            $text
        ) ?? $text;
    }
}
