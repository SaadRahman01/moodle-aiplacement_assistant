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
 * Tests for the access_check intent.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(access_check::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(lookup::class)]
final class access_check_test extends \advanced_testcase {
    /**
     * Build a request.
     *
     * @param \stdClass $user The requester.
     * @param \context $context Context.
     * @return request
     */
    private function request(\stdClass $user, \context $context): request {
        return new request($context, (int) $user->id, 'question');
    }

    /**
     * Rows keyed by label, for the check rows.
     *
     * @param \aiplacement_assistant\answer $answer The answer.
     * @param string $key Check key.
     * @return array|null The row, or null when absent.
     */
    private function check_row(\aiplacement_assistant\answer $answer, string $key): ?array {
        $label = get_string('check_' . $key, 'aiplacement_assistant');
        foreach ($answer->rows as $row) {
            if ($row['label'] === $label) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Whether a check row reports a problem.
     *
     * @param \aiplacement_assistant\answer $answer The answer.
     * @param string $key Check key.
     * @return bool
     */
    private function is_problem(\aiplacement_assistant\answer $answer, string $key): bool {
        $row = $this->check_row($answer, $key);
        $this->assertNotNull($row, "Missing check row $key");
        return str_starts_with($row['value'], get_string('check_problem', 'aiplacement_assistant'));
    }

    public function test_all_fine(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['shortname' => 'FINE1']);
        $ali = $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza']);
        $gen->enrol_user($ali->id, $course->id, 'student');
        $this->setAdminUser();
        $admin = get_admin();
        $answer = (new access_check())->handle(
            ['user' => 'Ali Raza', 'course' => 'FINE1'],
            $this->request($admin, \context_system::instance())
        );
        $this->assertSame(['problems' => 0], $answer->facts);
        $keys = ['user_suspended', 'enrolled', 'enrol_instance_enabled', 'enrolment_active', 'course_visible',
            'category_visible', 'has_role'];
        foreach ($keys as $key) {
            $this->assertFalse($this->is_problem($answer, $key), $key);
        }
        $this->assertCount(2, $answer->links);
    }

    public function test_suspended_enrolment(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['shortname' => 'SUSP1']);
        $ali = $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza']);
        $gen->enrol_user($ali->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->setAdminUser();
        $answer = (new access_check())->handle(
            ['user' => 'Ali Raza', 'course' => 'SUSP1'],
            $this->request(get_admin(), \context_system::instance())
        );
        $this->assertTrue($this->is_problem($answer, 'enrolment_active'));
        $this->assertSame(['problems' => 1], $answer->facts);
    }

    public function test_hidden_course(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['shortname' => 'HID1', 'visible' => 0]);
        $ali = $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza']);
        $gen->enrol_user($ali->id, $course->id, 'student');
        $this->setAdminUser();
        $answer = (new access_check())->handle(
            ['user' => 'Ali Raza', 'course' => 'HID1'],
            $this->request(get_admin(), \context_system::instance())
        );
        $this->assertTrue($this->is_problem($answer, 'course_visible'));
        $this->assertSame(['problems' => 1], $answer->facts);
    }

    /**
     * A course in a visible subcategory of a hidden category is reported as a category problem.
     */
    public function test_hidden_parent_category(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $parent = $gen->create_category(['name' => 'Parent']);
        $child = $gen->create_category(['name' => 'Child', 'parent' => $parent->id]);
        // Hide only the parent row, leaving the subcategory flagged visible.
        $DB->set_field('course_categories', 'visible', 0, ['id' => $parent->id]);
        \cache_helper::purge_by_event('changesincoursecat');
        $course = $gen->create_course(['shortname' => 'ANC1', 'category' => $child->id]);
        $ali = $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza']);
        $gen->enrol_user($ali->id, $course->id, 'student');
        $this->setAdminUser();
        $answer = (new access_check())->handle(
            ['user' => 'Ali Raza', 'course' => 'ANC1'],
            $this->request(get_admin(), \context_system::instance())
        );
        $this->assertTrue($this->is_problem($answer, 'category_visible'));
        $this->assertSame(['problems' => 1], $answer->facts);

        // Someone allowed to see hidden categories in the parent has no problem.
        $roleid = $gen->create_role();
        $parentctx = \context_coursecat::instance($parent->id);
        assign_capability('moodle/category:viewhiddencategories', CAP_ALLOW, $roleid, $parentctx);
        $gen->role_assign($roleid, $ali->id, $parentctx);
        $answer = (new access_check())->handle(
            ['user' => 'Ali Raza', 'course' => 'ANC1'],
            $this->request(get_admin(), \context_system::instance())
        );
        $this->assertFalse($this->is_problem($answer, 'category_visible'));
    }

    public function test_suspended_user_and_not_enrolled(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['shortname' => 'NE1']);
        $ali = $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza', 'suspended' => 1]);
        $this->setAdminUser();
        $answer = (new access_check())->handle(
            ['user' => 'Ali Raza', 'course' => 'NE1'],
            $this->request(get_admin(), \context_system::instance())
        );
        $this->assertTrue($this->is_problem($answer, 'user_suspended'));
        $this->assertTrue($this->is_problem($answer, 'enrolled'));
        $this->assertTrue($this->is_problem($answer, 'has_role'));
        $this->assertNull($this->check_row($answer, 'enrolment_active'));
        $this->assertSame(['problems' => 3], $answer->facts);
    }

    public function test_two_users_same_name_gives_candidates(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['shortname' => 'AMB1']);
        $gen->create_user(['firstname' => 'Sara', 'lastname' => 'Khan']);
        $gen->create_user(['firstname' => 'Sara', 'lastname' => 'Khan']);
        $this->setAdminUser();
        $answer = (new access_check())->handle(
            ['user' => 'Sara Khan', 'course' => 'AMB1'],
            $this->request(get_admin(), \context_system::instance())
        );
        $this->assertCount(2, $answer->rows);
        $this->assertSame(get_string('pick_user', 'aiplacement_assistant'), $answer->fallback);
        $this->assertNull($this->check_row($answer, 'enrolled'));
        $this->assertStringContainsString('/user/profile.php?id=', $answer->rows[0]['url']);
        $this->assertSame([], $answer->facts);
    }

    public function test_two_courses_gives_candidates(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $gen->create_course(['fullname' => 'Biology A', 'shortname' => 'BIOA']);
        $gen->create_course(['fullname' => 'Biology B', 'shortname' => 'BIOB']);
        $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza']);
        $this->setAdminUser();
        $answer = (new access_check())->handle(
            ['user' => 'Ali Raza', 'course' => 'Biology'],
            $this->request(get_admin(), \context_system::instance())
        );
        $this->assertCount(2, $answer->rows);
        $this->assertSame(get_string('pick_course', 'aiplacement_assistant'), $answer->fallback);
    }

    public function test_empty_course_uses_current_course(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $ali = $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza']);
        $gen->enrol_user($ali->id, $course->id, 'student');
        $this->setAdminUser();
        $answer = (new access_check())->handle(
            ['user' => 'Ali Raza', 'course' => ''],
            $this->request(get_admin(), \context_course::instance($course->id))
        );
        $this->assertSame(['problems' => 0], $answer->facts);

        $answer = (new access_check())->handle(
            ['user' => 'Ali Raza', 'course' => 'this course'],
            $this->request(get_admin(), \context_course::instance($course->id))
        );
        $this->assertSame(['problems' => 0], $answer->facts);
    }

    public function test_teacher_other_course_notfound(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $mine = $gen->create_course();
        $other = $gen->create_course(['shortname' => 'OTHER1']);
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $mine->id, 'editingteacher');
        $ali = $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza']);
        $gen->enrol_user($ali->id, $other->id, 'student');
        $this->setUser($teacher);
        $answer = (new access_check())->handle(
            ['user' => 'Ali Raza', 'course' => 'OTHER1'],
            $this->request($teacher, \context_course::instance($mine->id))
        );
        $this->assertSame(get_string('notfound_course', 'aiplacement_assistant'), $answer->fallback);
        $this->assertSame([], $answer->rows);
        $this->assertSame([], $answer->facts);
    }

    public function test_teacher_without_enrol_capability_cannot_find_outsider(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $outsider = $gen->create_user(['firstname' => 'Zed', 'lastname' => 'Outsider']);
        $coursectx = \context_course::instance($course->id);
        $request = $this->request($teacher, $coursectx);
        $this->setUser($teacher);

        // With enrol/manual:enrol (default for editing teacher) the site-wide search finds the outsider.
        $answer = (new access_check())->handle(['user' => 'Zed Outsider', 'course' => ''], $request);
        $this->assertSame(['problems' => 2], $answer->facts);

        // Without it, only enrolled users are searched.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('enrol/manual:enrol', CAP_PROHIBIT, $roleid, $coursectx->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $answer = (new access_check())->handle(['user' => 'Zed Outsider', 'course' => ''], $request);
        $this->assertSame(get_string('notfound_user', 'aiplacement_assistant'), $answer->fallback);
        $this->assertSame([], $answer->rows);
    }

    public function test_scoped_search_finds_suspended_enrolled_user(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $ali = $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza']);
        $gen->enrol_user($ali->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $coursectx = \context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('enrol/manual:enrol', CAP_PROHIBIT, $roleid, $coursectx->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);
        $answer = (new access_check())->handle(
            ['user' => 'Ali', 'course' => ''],
            $this->request($teacher, $coursectx)
        );
        $this->assertSame(['problems' => 1], $answer->facts);
    }

    public function test_scoped_candidates_link_to_course_profile(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        foreach ([1, 2] as $unused) {
            $u = $gen->create_user(['firstname' => 'Sara', 'lastname' => 'Khan']);
            $gen->enrol_user($u->id, $course->id, 'student');
        }
        $coursectx = \context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('enrol/manual:enrol', CAP_PROHIBIT, $roleid, $coursectx->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);
        $answer = (new access_check())->handle(['user' => 'Sara Khan', 'course' => ''], $this->request($teacher, $coursectx));
        $this->assertCount(2, $answer->rows);
        $this->assertStringContainsString('/user/view.php?id=', $answer->rows[0]['url']);
        $this->assertStringContainsString('course=' . $course->id, $answer->rows[0]['url']);
        $this->assertSame('', $answer->rows[0]['value']);
    }

    public function test_users_lookup_excludes_deleted_and_guest(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $gen->create_user(['firstname' => 'Gone', 'lastname' => 'Person', 'deleted' => 1]);
        $live = $gen->create_user(['firstname' => 'Here', 'lastname' => 'Person', 'username' => 'hereperson']);
        $this->assertCount(1, lookup::users('Person'));
        $this->assertSame([(int) $live->id], array_map('intval', array_keys(lookup::users('hereperson'))));
        $this->assertSame([], lookup::users('Guest user'));
    }

    public function test_availability(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');
        $ctx = \context_course::instance($course->id);
        $this->assertTrue((new access_check())->is_available($this->request($teacher, $ctx)));
        $this->assertFalse((new access_check())->is_available($this->request($student, $ctx)));
        $this->assertTrue((new access_check())->is_available($this->request(get_admin(), \context_system::instance())));
    }

    /**
     * Run the check for Ali in a course as admin.
     *
     * @param string $shortname Course short name.
     * @return \aiplacement_assistant\answer
     */
    private function check_ali(string $shortname): \aiplacement_assistant\answer {
        $this->setAdminUser();
        return (new access_check())->handle(
            ['user' => 'Ali Raza', 'course' => $shortname],
            $this->request(get_admin(), \context_system::instance())
        );
    }

    public function test_mixed_suspended_enabled_and_active_disabled_is_problem(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['shortname' => 'MIX1']);
        $ali = $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza']);
        $gen->enrol_user($ali->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $self = enrol_get_plugin('self');
        $instanceid = $self->add_instance($course, ['status' => ENROL_INSTANCE_DISABLED]);
        $instance = $this->get_instance($instanceid);
        $self->enrol_user($instance, $ali->id, 5, 0, 0, ENROL_USER_ACTIVE);
        $answer = $this->check_ali('MIX1');
        $this->assertTrue($this->is_problem($answer, 'enrolment_active'));
        $this->assertFalse($this->is_problem($answer, 'enrol_instance_enabled'));
        $this->assertGreaterThanOrEqual(1, $answer->facts['problems']);
        $this->assertStringContainsString(
            get_string('check_detail_suspended', 'aiplacement_assistant'),
            $this->check_row($answer, 'enrolment_active')['value']
        );
    }

    /**
     * Fetch an enrol instance record.
     *
     * @param int $id Instance id.
     * @return \stdClass
     */
    private function get_instance(int $id): \stdClass {
        global $DB;
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_expired_enrolment(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['shortname' => 'EXP1']);
        $ali = $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza']);
        $gen->enrol_user($ali->id, $course->id, 'student', 'manual', time() - 2 * DAYSECS, time() - DAYSECS);
        $answer = $this->check_ali('EXP1');
        $this->assertTrue($this->is_problem($answer, 'enrolment_active'));
        $this->assertStringContainsString(
            get_string('check_detail_expired', 'aiplacement_assistant'),
            $this->check_row($answer, 'enrolment_active')['value']
        );
    }

    public function test_not_yet_started_enrolment(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['shortname' => 'NYS1']);
        $ali = $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza']);
        $gen->enrol_user($ali->id, $course->id, 'student', 'manual', time() + DAYSECS);
        $answer = $this->check_ali('NYS1');
        $this->assertTrue($this->is_problem($answer, 'enrolment_active'));
        $this->assertStringContainsString(
            get_string('check_detail_notstarted', 'aiplacement_assistant'),
            $this->check_row($answer, 'enrolment_active')['value']
        );
    }

    public function test_disabled_instance_only(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['shortname' => 'DIS1']);
        $ali = $gen->create_user(['firstname' => 'Ali', 'lastname' => 'Raza']);
        $gen->enrol_user($ali->id, $course->id, 'student');
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['courseid' => $course->id, 'enrol' => 'manual']);
        $answer = $this->check_ali('DIS1');
        $this->assertTrue($this->is_problem($answer, 'enrol_instance_enabled'));
        $this->assertTrue($this->is_problem($answer, 'enrolment_active'));
    }
}
