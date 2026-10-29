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
use aiplacement_assistant\fake_ai_bridge;
use aiplacement_assistant\request;

/**
 * Tests for the teacher intents.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(not_submitted::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(inactive_users::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(forum_summary::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(lookup::class)]
final class teacher_intents_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/ai/placement/assistant/tests/fixtures/fake_ai_bridge.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
    }

    /**
     * Build a request.
     *
     * @param \stdClass $user The user.
     * @param \context $context Context.
     * @return request
     */
    private function request(\stdClass $user, \context $context): request {
        return new request($context, (int) $user->id, 'question');
    }

    /**
     * Install a fake AI bridge.
     *
     * @param array $responses Queued responses.
     * @return fake_ai_bridge
     */
    private function fake(array $responses = []): fake_ai_bridge {
        $fake = new fake_ai_bridge();
        $fake->responses = $responses;
        \core\di::set(ai_bridge::class, $fake);
        return $fake;
    }

    /**
     * Set a submission status for a user.
     *
     * @param \stdClass $assigncm Module record from the generator.
     * @param int $userid User id.
     * @param string $status Submission status.
     */
    private function set_status(\stdClass $assigncm, int $userid, string $status): void {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid($assigncm->cmid, 'assign');
        $assign = new \assign(\context_module::instance($cm->id), $cm, $course);
        $submission = $assign->get_user_submission($userid, true);
        $DB->set_field('assign_submission', 'status', $status, ['id' => $submission->id]);
    }

    /**
     * Students who have not submitted are listed with their status.
     */
    public function test_not_submitted(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $s1 = $gen->create_and_enrol($course, 'student', ['firstname' => 'Sam', 'lastname' => 'Submitted']);
        $s2 = $gen->create_and_enrol($course, 'student', ['firstname' => 'Dana', 'lastname' => 'Draft']);
        $s3 = $gen->create_and_enrol($course, 'student', ['firstname' => 'Nora', 'lastname' => 'None']);
        $assigncm = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Essay one']);
        $this->set_status($assigncm, $s1->id, ASSIGN_SUBMISSION_STATUS_SUBMITTED);
        $this->set_status($assigncm, $s2->id, ASSIGN_SUBMISSION_STATUS_DRAFT);
        $this->setUser($teacher);

        $coursectx = \context_course::instance($course->id);
        $intent = new not_submitted();
        $this->assertTrue($intent->is_available($this->request($teacher, $coursectx)));
        $answer = $intent->handle(['activity' => 'essay'], $this->request($teacher, $coursectx));
        $this->assertCount(2, $answer->rows);
        $byname = array_column($answer->rows, 'value', 'label');
        $this->assertSame(get_string('status_draft', 'aiplacement_assistant'), $byname[fullname($s2)]);
        $this->assertSame(get_string('status_none', 'aiplacement_assistant'), $byname[fullname($s3)]);
        $this->assertArrayNotHasKey(fullname($s1), $byname);
        $grading = '/mod/assign/view.php?id=' . $assigncm->cmid . '&action=grading';
        $this->assertStringContainsString($grading, $answer->rows[0]['url']);
        $this->assertSame(['missing' => 2, 'total' => 3, 'activity' => 'Essay one'], $answer->facts);

        // From inside the module, with no term, that module is used.
        $answer = $intent->handle([], $this->request($teacher, \context_module::instance($assigncm->cmid)));
        $this->assertCount(2, $answer->rows);

        // Unknown activity.
        $answer = $intent->handle(['activity' => 'nothing like it'], $this->request($teacher, $coursectx));
        $this->assertSame([], $answer->rows);
        $this->assertSame(get_string('notfound_activity', 'aiplacement_assistant'), $answer->fallback);
    }

    /**
     * Students cannot use it; it needs a course.
     */
    public function test_not_submitted_availability(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $intent = new not_submitted();
        $this->assertFalse($intent->is_available($this->request($student, \context_course::instance($course->id))));
        $this->assertFalse($intent->is_available($this->request($teacher, \context_system::instance())));
    }

    /**
     * Ambiguous activity names give candidates and no data.
     */
    public function test_not_submitted_ambiguous(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $gen->create_and_enrol($course, 'student');
        $a = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Lab report 1']);
        $b = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Lab report 2']);
        $this->setUser($teacher);

        $coursectx = \context_course::instance($course->id);
        $answer = (new not_submitted())->handle(['activity' => 'LAB'], $this->request($teacher, $coursectx));
        $this->assertSame(['Lab report 1', 'Lab report 2'], array_column($answer->rows, 'label'));
        $this->assertSame([''], array_unique(array_column($answer->rows, 'value')));
        $this->assertStringContainsString('/mod/assign/view.php?id=' . $a->cmid, $answer->rows[0]['url']);
        $this->assertStringContainsString('/mod/assign/view.php?id=' . $b->cmid, $answer->rows[1]['url']);
        $this->assertSame([], $answer->facts);
        $this->assertSame(get_string('pick_activity', 'aiplacement_assistant'), $answer->fallback);
    }

    /**
     * In separate groups, a teacher without access to all groups only sees their own group.
     */
    public function test_not_submitted_separate_groups(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $teacher = $gen->create_and_enrol($course, 'teacher');
        $ina = $gen->create_and_enrol($course, 'student', ['firstname' => 'Alpha', 'lastname' => 'Groupa']);
        $inb = $gen->create_and_enrol($course, 'student', ['firstname' => 'Beta', 'lastname' => 'Groupb']);
        $ga = $gen->create_group(['courseid' => $course->id]);
        $gb = $gen->create_group(['courseid' => $course->id]);
        $gen->create_group_member(['groupid' => $ga->id, 'userid' => $teacher->id]);
        $gen->create_group_member(['groupid' => $ga->id, 'userid' => $ina->id]);
        $gen->create_group_member(['groupid' => $gb->id, 'userid' => $inb->id]);
        $gen->create_module('assign', ['course' => $course->id, 'name' => 'Grouped', 'groupmode' => SEPARATEGROUPS]);
        $teacherrole = \core\di::get(\moodle_database::class)->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $teacherrole, \context_course::instance($course->id));
        $this->setUser($teacher);

        $coursectx = \context_course::instance($course->id);
        $answer = (new not_submitted())->handle(['activity' => 'grouped'], $this->request($teacher, $coursectx));
        $labels = array_column($answer->rows, 'label');
        $this->assertContains(fullname($ina), $labels);
        $this->assertNotContains(fullname($inb), $labels);
        $this->assertSame(1, $answer->facts['total']);
    }

    /**
     * Blind marking hides identities unless the user may view blind details.
     */
    public function test_not_submitted_blind_marking(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'teacher');
        $s1 = $gen->create_and_enrol($course, 'student', ['firstname' => 'Blinda', 'lastname' => 'Hiddenson']);
        $s2 = $gen->create_and_enrol($course, 'student', ['firstname' => 'Masko', 'lastname' => 'Veilwright']);
        $assigncm = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Blind essay', 'blindmarking' => 1]);
        $this->setUser($teacher);
        $coursectx = \context_course::instance($course->id);
        [$c, $cm] = get_course_and_cm_from_cmid($assigncm->cmid, 'assign');
        $assign = new \assign(\context_module::instance($cm->id), $cm, $c);

        $answer = (new not_submitted())->handle(['activity' => 'blind'], $this->request($teacher, $coursectx));
        $expected = [
            get_string('hiddenuser', 'assign') . $assign->get_uniqueid_for_user($s1->id),
            get_string('hiddenuser', 'assign') . $assign->get_uniqueid_for_user($s2->id),
        ];
        sort($expected, SORT_NATURAL);
        $this->assertSame($expected, array_column($answer->rows, 'label'));
        $dump = json_encode($answer);
        foreach (['Blinda', 'Hiddenson', 'Masko', 'Veilwright'] as $part) {
            $this->assertStringNotContainsString($part, $dump);
        }

        $teacherrole = \core\di::get(\moodle_database::class)->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('mod/assign:viewblinddetails', CAP_ALLOW, $teacherrole, $coursectx);
        $answer = (new not_submitted())->handle(['activity' => 'blind'], $this->request($teacher, $coursectx));
        $labels = array_column($answer->rows, 'label');
        $this->assertContains(fullname($s1), $labels);
        $this->assertContains(fullname($s2), $labels);
    }

    /**
     * Users who have not accessed the course within the window are listed.
     */
    public function test_inactive_users(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $old = $gen->create_and_enrol($course, 'student', ['firstname' => 'Old', 'lastname' => 'Timer']);
        $never = $gen->create_and_enrol($course, 'student', ['firstname' => 'Never', 'lastname' => 'Seen']);
        $recent = $gen->create_and_enrol($course, 'student', ['firstname' => 'Recent', 'lastname' => 'Visitor']);
        $DB->insert_record('user_lastaccess', ['userid' => $old->id, 'courseid' => $course->id,
            'timeaccess' => time() - 20 * DAYSECS]);
        $DB->insert_record('user_lastaccess', ['userid' => $recent->id, 'courseid' => $course->id,
            'timeaccess' => time() - DAYSECS - HOURSECS]);
        $this->setUser($teacher);

        $coursectx = \context_course::instance($course->id);
        $intent = new inactive_users();
        $this->assertTrue($intent->is_available($this->request($teacher, $coursectx)));
        $this->assertFalse($intent->is_available($this->request($teacher, \context_system::instance())));
        // Students can view participants by default but not the participation report.
        $this->assertFalse($intent->is_available($this->request($old, $coursectx)));
        $this->setUser($old);
        $answer = $intent->handle(['days' => 14], $this->request($old, $coursectx));
        $this->assertSame([], $answer->rows);
        $this->assertSame([], $answer->facts);
        $this->setUser($teacher);

        $answer = $intent->handle(['days' => 14], $this->request($teacher, $coursectx));
        $byname = array_column($answer->rows, 'value', 'label');
        $this->assertCount(2, $answer->rows);
        $this->assertArrayHasKey(fullname($old), $byname);
        $this->assertSame(get_string('never'), $byname[fullname($never)]);
        $this->assertArrayNotHasKey(fullname($recent), $byname);
        $this->assertSame(['count' => 2, 'days' => 14], $answer->facts);

        // Junk days falls back to 14; a short window includes the recent user too.
        $answer = $intent->handle(['days' => 0], $this->request($teacher, $coursectx));
        $this->assertSame(14, $answer->facts['days']);
        $answer = $intent->handle(['days' => 1000], $this->request($teacher, $coursectx));
        $this->assertSame(14, $answer->facts['days']);
        $answer = $intent->handle([], $this->request($teacher, $coursectx));
        $this->assertSame(14, $answer->facts['days']);
        $answer = $intent->handle(['days' => 1], $this->request($teacher, $coursectx));
        $this->assertCount(3, $answer->rows);
    }

    /**
     * Last access hidden from the user gives the fallback and no rows.
     */
    public function test_inactive_users_hidden_field(): void {
        global $CFG;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'teacher');
        $gen->create_and_enrol($course, 'student');
        $CFG->hiddenuserfields = 'lastaccess';
        $coursectx = \context_course::instance($course->id);
        $teacherrole = \core\di::get(\moodle_database::class)->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('moodle/course:viewhiddenuserfields', CAP_PROHIBIT, $teacherrole, $coursectx);
        $this->setUser($teacher);

        $answer = (new inactive_users())->handle([], $this->request($teacher, $coursectx));
        $this->assertSame([], $answer->rows);
        $this->assertSame([], $answer->facts);
        $this->assertSame(get_string('lastaccess_hidden', 'aiplacement_assistant'), $answer->fallback);
    }

    /**
     * Forum posts are anonymised for the AI and real names restored afterwards.
     */
    public function test_forum_summary(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher', ['firstname' => 'Tina', 'lastname' => 'Teacher']);
        $zelda = $gen->create_and_enrol($course, 'student', ['firstname' => 'Zelda', 'lastname' => 'Zebra',
            'email' => 'zelda@example.com']);
        $forum = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Class forum']);
        $forumgen = $gen->get_plugin_generator('mod_forum');
        $discussion = $forumgen->create_discussion([
            'course' => $course->id,
            'forum' => $forum->id,
            'userid' => $zelda->id,
            'name' => 'Question on X',
            'message' => '<p>How does <b>X</b> work &amp; why?</p>',
        ]);
        $forumgen->create_post([
            'discussion' => $discussion->id,
            'parent' => $discussion->firstpost,
            'userid' => $teacher->id,
            'message' => 'Thanks Zelda Zebra, X works like Y.',
        ]);
        $this->setUser($teacher);
        $fake = $this->fake(["- Person 1 asked about X\n- Person 2 answered"]);

        $coursectx = \context_course::instance($course->id);
        $intent = new forum_summary();
        $this->assertTrue($intent->is_available($this->request($teacher, $coursectx)));
        $answer = $intent->handle(['forum' => 'class'], $this->request($teacher, $coursectx));

        $this->assertCount(1, $fake->prompts);
        $prompt = $fake->prompts[0];
        $this->assertStringNotContainsString('Zelda', $prompt);
        $this->assertStringNotContainsString('Zebra', $prompt);
        $this->assertStringNotContainsString('Tina', $prompt);
        $this->assertStringNotContainsString('zelda@example.com', $prompt);
        $this->assertStringNotContainsString('<b>', $prompt);
        $this->assertStringContainsString('Person 1', $prompt);
        $this->assertStringContainsString('Person 2', $prompt);
        $this->assertStringContainsString('How does X work & why?', $prompt);
        $this->assertStringContainsString('<<<DATA forum>>>', $prompt);

        $this->assertStringContainsString('Zelda Zebra asked about X', $answer->text);
        $this->assertStringContainsString('Tina Teacher answered', $answer->text);
        $this->assertSame("- Person 1 asked about X\n- Person 2 answered", $answer->historytext);
        $this->assertSame([], $answer->rows);
        $this->assertStringContainsString('/mod/forum/view.php?id=' . $forum->cmid, $answer->links[0]['url']);
    }

    /**
     * Emails, enrolled users' and the requester's full names, and shared first names never reach the AI.
     */
    public function test_forum_summary_scrubbing(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher', ['firstname' => 'Rhea', 'lastname' => 'Requester']);
        $quiet = $gen->create_and_enrol($course, 'student', ['firstname' => 'Quentin', 'lastname' => 'Quietly',
            'middlename' => 'Mid', 'alternatename' => 'Quinn']);
        $j1 = $gen->create_and_enrol($course, 'student', ['firstname' => 'John', 'lastname' => 'Alpha']);
        $j2 = $gen->create_and_enrol($course, 'student', ['firstname' => 'John', 'lastname' => 'Beta']);
        $third = $gen->create_and_enrol($course, 'student', ['firstname' => 'Third', 'lastname' => 'Writer']);
        $forum = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Scrub']);
        $forumgen = $gen->get_plugin_generator('mod_forum');
        $d = $forumgen->create_discussion(['course' => $course->id, 'forum' => $forum->id, 'userid' => $j1->id,
            'name' => 'Ask quentin.q@example.org', 'message' => 'Mail me: j.alpha+forum@mail.example.com please']);
        $forumgen->create_post(['discussion' => $d->id, 'parent' => $d->firstpost, 'userid' => $j2->id,
            'message' => 'I agree with Quentin Quietly and Quietly Quentin and Quentin Mid Quietly and Quinn Quietly.']);
        $forumgen->create_post(['discussion' => $d->id, 'parent' => $d->firstpost, 'userid' => $third->id,
            'message' => 'John said ask Rhea Requester. Person and 2024 stay.']);
        $this->setUser($teacher);
        $fake = $this->fake(['Summary']);

        (new forum_summary())->handle(['forum' => 'scrub'], $this->request($teacher, \context_course::instance($course->id)));
        $prompt = $fake->prompts[0];
        foreach (['@', 'example', 'Quentin', 'Quietly', 'Quinn', 'Rhea', 'Requester', 'John', 'Alpha', 'Beta'] as $leak) {
            $this->assertStringNotContainsString($leak, $prompt);
        }
        $this->assertStringContainsString('[email]', $prompt);
        $agree = 'I agree with a participant and a participant and a participant and a participant.';
        $this->assertStringContainsString($agree, $prompt);
        $this->assertStringContainsString('a participant said ask a participant.', $prompt);
        $this->assertStringContainsString('Person and 2024 stay.', $prompt);
    }

    /**
     * Scrubbing stays fast in a course with many enrolled users.
     */
    public function test_forum_summary_scrubbing_large_course(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $studentrole = \core\di::get(\moodle_database::class)->get_field('role', 'id', ['shortname' => 'student']);
        $users = [];
        for ($i = 0; $i < 300; $i++) {
            $user = $gen->create_user(['firstname' => 'Bulk' . $i, 'lastname' => 'Learner' . $i]);
            $gen->enrol_user($user->id, $course->id, $studentrole);
            $users[] = $user;
        }
        $forum = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Large']);
        $gen->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id, 'forum' => $forum->id,
            'userid' => $users[0]->id, 'message' => str_repeat('Thanks Bulk299 Learner299 and Bulk150 Learner150. ', 100)]);
        $this->setUser($teacher);
        $fake = $this->fake(['Summary']);

        $start = microtime(true);
        (new forum_summary())->handle(['forum' => 'large'], $this->request($teacher, \context_course::instance($course->id)));
        $this->assertLessThan(5.0, microtime(true) - $start);
        $this->assertStringNotContainsString('Learner299', $fake->prompts[0]);
        $this->assertStringNotContainsString('Learner150', $fake->prompts[0]);
        $this->assertStringContainsString('Thanks a participant and a participant.', $fake->prompts[0]);
    }

    /**
     * Person 1 is not substituted inside Person 10 and unknown labels are left alone.
     */
    public function test_forum_summary_label_mapping(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $forum = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Busy']);
        $forumgen = $gen->get_plugin_generator('mod_forum');
        for ($i = 1; $i <= 10; $i++) {
            $user = $gen->create_and_enrol($course, 'student', ['firstname' => 'User', 'lastname' => str_repeat(chr(64 + $i), 2)]);
            $forumgen->create_discussion([
                'course' => $course->id,
                'forum' => $forum->id,
                'userid' => $user->id,
                'message' => 'Message number ' . $i,
            ]);
        }
        $this->setUser($teacher);
        $this->fake(['Person 10 and Person 1 and Person 11 and Persons 1']);

        $coursectx = \context_course::instance($course->id);
        $answer = (new forum_summary())->handle(['forum' => 'busy'], $this->request($teacher, $coursectx));
        $this->assertMatchesRegularExpression('/^User ([A-J])\1 and User ([A-J])\2 and Person 11 and Persons 1$/', $answer->text);
    }

    /**
     * In a separate-groups forum, other groups' posts are never sent.
     */
    public function test_forum_summary_separate_groups(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'teacher');
        $ina = $gen->create_and_enrol($course, 'student');
        $inb = $gen->create_and_enrol($course, 'student');
        $ga = $gen->create_group(['courseid' => $course->id]);
        $gb = $gen->create_group(['courseid' => $course->id]);
        $gen->create_group_member(['groupid' => $ga->id, 'userid' => $teacher->id]);
        $gen->create_group_member(['groupid' => $ga->id, 'userid' => $ina->id]);
        $gen->create_group_member(['groupid' => $gb->id, 'userid' => $inb->id]);
        $forum = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Groups forum', 'groupmode' => SEPARATEGROUPS]);
        $forumgen = $gen->get_plugin_generator('mod_forum');
        $forumgen->create_discussion(['course' => $course->id, 'forum' => $forum->id, 'userid' => $ina->id,
            'groupid' => $ga->id, 'message' => 'Visible group A text']);
        $forumgen->create_discussion(['course' => $course->id, 'forum' => $forum->id, 'userid' => $inb->id,
            'groupid' => $gb->id, 'message' => 'Secret group B text']);
        $coursectx = \context_course::instance($course->id);
        $teacherrole = \core\di::get(\moodle_database::class)->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $teacherrole, $coursectx);
        $this->setUser($teacher);
        $fake = $this->fake(['Summary']);

        (new forum_summary())->handle([], $this->request($teacher, \context_module::instance($forum->cmid)));
        $this->assertStringContainsString('Visible group A text', $fake->prompts[0]);
        $this->assertStringNotContainsString('Secret group B text', $fake->prompts[0]);
    }

    /**
     * Private replies not meant for the user and Q&A replies before posting are excluded.
     */
    public function test_forum_summary_private_and_qanda(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $other = $gen->create_and_enrol($course, 'student');
        $forumgen = $gen->get_plugin_generator('mod_forum');

        $forum = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Normal']);
        $d = $forumgen->create_discussion(['course' => $course->id, 'forum' => $forum->id, 'userid' => $other->id,
            'message' => 'Public opener']);
        $forumgen->create_post(['discussion' => $d->id, 'parent' => $d->firstpost, 'userid' => $teacher->id,
            'privatereplyto' => $other->id, 'message' => 'Private feedback']);

        $qanda = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Quiz QA', 'type' => 'qanda']);
        $q = $forumgen->create_discussion(['course' => $course->id, 'forum' => $qanda->id, 'userid' => $teacher->id,
            'message' => 'The question']);
        $forumgen->create_post(['discussion' => $q->id, 'parent' => $q->firstpost, 'userid' => $other->id,
            'message' => 'Hidden answer']);

        $this->setUser($student);
        $fake = $this->fake(['One', 'Two']);
        $coursectx = \context_course::instance($course->id);
        $intent = new forum_summary();
        $this->assertTrue($intent->is_available($this->request($student, $coursectx)));

        $intent->handle(['forum' => 'normal'], $this->request($student, $coursectx));
        $this->assertStringContainsString('Public opener', $fake->prompts[0]);
        $this->assertStringNotContainsString('Private feedback', $fake->prompts[0]);

        $intent->handle(['forum' => 'quiz'], $this->request($student, $coursectx));
        $this->assertStringContainsString('The question', $fake->prompts[1]);
        $this->assertStringNotContainsString('Hidden answer', $fake->prompts[1]);
    }

    /**
     * AI failure gives the fixed failure text; empty forums do not call the AI.
     */
    public function test_forum_summary_failure_and_empty(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $forum = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Talk']);
        $empty = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Quiet']);
        $gen->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id, 'forum' => $forum->id,
            'userid' => $teacher->id, 'message' => 'Hello']);
        $this->setUser($teacher);
        $fake = $this->fake([null]);
        $coursectx = \context_course::instance($course->id);

        $answer = (new forum_summary())->handle(['forum' => 'talk'], $this->request($teacher, $coursectx));
        $failed = get_string('summary_failed', 'aiplacement_assistant');
        $this->assertSame($failed, $answer->text);
        $this->assertSame($failed, $answer->fallback);
        $this->assertSame([], $answer->facts);

        $answer = (new forum_summary())->handle(['forum' => 'quiet'], $this->request($teacher, $coursectx));
        $this->assertCount(1, $fake->prompts);
        $this->assertSame(['posts' => 0, 'forum' => 'Quiet'], $answer->facts);
        $this->assertSame('', $answer->text);
    }

    /**
     * Hidden activities are not matched for users who cannot see them.
     */
    public function test_activity_in_course_visibility(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $gen->create_module('forum', ['course' => $course->id, 'name' => 'Open forum']);
        $gen->create_module('forum', ['course' => $course->id, 'name' => 'Hidden forum', 'visible' => 0]);
        $this->setUser($student);
        $coursectx = \context_course::instance($course->id);

        $names = array_map(fn($cm) => $cm->name, array_values(lookup::activity_in_course($coursectx, 'forum', 'FORUM')));
        $this->assertSame(['Open forum'], $names);
        $this->assertCount(1, lookup::activity_in_course($coursectx, 'forum', ''));
        $this->assertSame([], lookup::activity_in_course($coursectx, 'assign', ''));
    }
}
