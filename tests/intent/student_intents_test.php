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
 * Tests for the student intents.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(due_this_week::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(my_grade::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(unfinished_activities::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(lookup::class)]
final class student_intents_test extends \advanced_testcase {
    /**
     * Build a request.
     *
     * @param \stdClass $user The user.
     * @param \context|null $context Context, system by default.
     * @return request
     */
    private function request(\stdClass $user, ?\context $context = null): request {
        return new request($context ?? \context_system::instance(), (int) $user->id, 'question');
    }

    /**
     * Only own enrolled-course deadlines within the window are listed.
     */
    public function test_due_this_week(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $c1 = $gen->create_course(['shortname' => 'MINE']);
        $c2 = $gen->create_course();
        $gen->enrol_user($student->id, $c1->id, 'student');
        $assign = $gen->get_plugin_generator('mod_assign');
        $assign->create_instance(['course' => $c1->id, 'name' => 'Soon', 'duedate' => time() + 3 * DAYSECS]);
        $assign->create_instance(['course' => $c1->id, 'name' => 'Later', 'duedate' => time() + 10 * DAYSECS]);
        $assign->create_instance(['course' => $c2->id, 'name' => 'Other', 'duedate' => time() + 2 * DAYSECS]);
        $this->setUser($student);

        $answer = (new due_this_week())->handle(['days' => 7], $this->request($student));
        $this->assertCount(1, $answer->rows);
        $this->assertStringContainsString('Soon', $answer->rows[0]['label']);
        $this->assertStringContainsString('(MINE)', $answer->rows[0]['label']);
        $this->assertSame(['count' => 1, 'days' => 7], $answer->facts);

        // Junk days falls back to the default of 7.
        $answer = (new due_this_week())->handle(['days' => 0], $this->request($student));
        $this->assertSame(7, $answer->facts['days']);
        $this->assertCount(1, $answer->rows);

        $answer = (new due_this_week())->handle(['days' => 14], $this->request($student));
        $this->assertCount(2, $answer->rows);
    }

    /**
     * Deadlines from a course where the student's enrolment is suspended are not listed, as in the Timeline block.
     */
    public function test_due_excludes_suspended_enrolment(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $active = $gen->create_course();
        $suspended = $gen->create_course();
        $gen->enrol_user($student->id, $active->id, 'student');
        $gen->enrol_user($student->id, $suspended->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $assign = $gen->get_plugin_generator('mod_assign');
        $assign->create_instance(['course' => $active->id, 'name' => 'Active one', 'duedate' => time() + DAYSECS]);
        $assign->create_instance(['course' => $suspended->id, 'name' => 'Suspended one', 'duedate' => time() + DAYSECS]);
        $this->setUser($student);

        $answer = (new due_this_week())->handle([], $this->request($student));
        $labels = implode("\n", array_column($answer->rows, 'label'));
        $this->assertStringContainsString('Active one', $labels);
        $this->assertStringNotContainsString('Suspended one', $labels);
    }

    /**
     * Nothing due gives the none fallback; guests are excluded.
     */
    public function test_due_none_and_availability(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $answer = (new due_this_week())->handle([], $this->request($student));
        $this->assertSame([], $answer->rows);
        $this->assertSame(0, $answer->facts['count']);
        $this->assertSame(get_string('due_none', 'aiplacement_assistant', (object) ['days' => 7]), $answer->fallback);

        $this->assertTrue((new due_this_week())->is_available($this->request($student)));
        $this->assertFalse((new due_this_week())->is_available($this->request(guest_user())));
    }

    /**
     * A visible grade is shown in the row.
     */
    public function test_my_grade(): void {
        $this->resetAfterTest();
        [$student, $course] = $this->grade_setup();
        $answer = (new my_grade())->handle(['course' => 'grade course'], $this->request($student));
        $this->assertCount(1, $answer->rows);
        $this->assertStringContainsString('80', $answer->rows[0]['value']);
        $this->assertStringContainsString('/grade/report/user/index.php?id=' . $course->id, $answer->rows[0]['url']);
        // The grade itself is never sent to the AI, so there is nothing to phrase and the fixed text is shown.
        $this->assertSame([], $answer->facts);
        $this->assertSame(get_string('grade_found', 'aiplacement_assistant', 'Grade course'), $answer->fallback);
    }

    /**
     * Course taken from the request context when no term is given.
     */
    public function test_my_grade_from_course_context(): void {
        $this->resetAfterTest();
        [$student, $course] = $this->grade_setup();
        $answer = (new my_grade())->handle(['course' => ''], $this->request($student, \context_course::instance($course->id)));
        $this->assertCount(1, $answer->rows);
        $answer = (new my_grade())->handle([], $this->request($student));
        $this->assertSame(get_string('notfound_course', 'aiplacement_assistant'), $answer->fallback);
    }

    /**
     * A hidden course total is not revealed.
     */
    public function test_my_grade_hidden(): void {
        $this->resetAfterTest();
        [$student, $course] = $this->grade_setup();
        \grade_item::fetch_course_item($course->id)->set_hidden(1);
        $answer = (new my_grade())->handle(['course' => 'Grade course'], $this->request($student));
        $this->assertSame(get_string('grade_hidden', 'aiplacement_assistant'), $answer->rows[0]['value']);
        $this->assertStringNotContainsString('80', $answer->rows[0]['value']);
    }

    /**
     * Ambiguous course names produce candidates and no grades.
     */
    public function test_my_grade_ambiguous_and_unknown(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $a = $gen->create_course(['fullname' => 'Chemistry A']);
        $b = $gen->create_course(['fullname' => 'Chemistry B']);
        $notmine = $gen->create_course(['fullname' => 'Chemistry C']);
        $gen->enrol_user($student->id, $a->id, 'student');
        $gen->enrol_user($student->id, $b->id, 'student');
        $this->setUser($student);

        $answer = (new my_grade())->handle(['course' => 'chem'], $this->request($student));
        $this->assertCount(2, $answer->rows);
        $this->assertSame(['Chemistry A', 'Chemistry B'], array_column($answer->rows, 'label'));
        $this->assertSame([''], array_unique(array_column($answer->rows, 'value')));
        $this->assertSame(get_string('pick_course', 'aiplacement_assistant'), $answer->fallback);
        $this->assertSame([], $answer->facts);

        $answer = (new my_grade())->handle(['course' => 'Chemistry C'], $this->request($student));
        $this->assertSame([], $answer->rows);
        $this->assertSame(get_string('notfound_course', 'aiplacement_assistant'), $answer->fallback);
    }

    /**
     * Deictic course terms such as "this course" mean the current course.
     *
     * @dataProvider deictic_course_provider
     * @param string $term Course term from the router.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('deictic_course_provider')]
    public function test_my_grade_deictic_course(string $term): void {
        $this->resetAfterTest();
        [$student, $course] = $this->grade_setup();
        $ctx = \context_course::instance($course->id);
        $answer = (new my_grade())->handle(['course' => $term], $this->request($student, $ctx));
        $this->assertCount(1, $answer->rows);
        $this->assertStringContainsString('80', $answer->rows[0]['value']);
    }

    /**
     * Deictic course terms.
     *
     * @return array
     */
    public static function deictic_course_provider(): array {
        return [
            'this course' => ['this course'],
            'my course' => ['My Course'],
            'the current course' => ['the current course'],
            'our class' => ['our class'],
            'the grade course' => ['the Grade course'],
        ];
    }

    /**
     * Normalising terms strips leading deictic words and one trailing type noun.
     *
     * @dataProvider normalise_term_provider
     * @param string $term Raw term.
     * @param string $expected Normalised term.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('normalise_term_provider')]
    public function test_normalise_term(string $term, string $expected): void {
        $this->assertSame($expected, lookup::normalise_term($term));
    }

    /**
     * Terms and their normalised form.
     *
     * @return array
     */
    public static function normalise_term_provider(): array {
        return [
            ['this course', ''],
            ['  the   forum ', ''],
            ['my current course', ''],
            ['this assignment', ''],
            ['the Course Chat forum', 'Course Chat'],
            ['Chemistry', 'Chemistry'],
            ['Biology course', 'Biology'],
            ['Thermodynamics', 'Thermodynamics'],
            ['', ''],
        ];
    }

    /**
     * A course name with an ampersand is not escaped in the row or the fallback.
     */
    public function test_my_grade_ampersand_not_pre_escaped(): void {
        $this->resetAfterTest();
        [$student, $course] = $this->grade_setup();
        update_course((object) ['id' => $course->id, 'fullname' => 'Science & Tech']);
        $answer = (new my_grade())->handle(['course' => 'Science & Tech'], $this->request($student));
        $this->assertSame('Science & Tech', $answer->rows[0]['label']);
        $this->assertStringNotContainsString('&amp;', $answer->fallback);
    }

    /**
     * Only incomplete, visible, completion-tracked activities are listed.
     */
    public function test_unfinished_activities(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $course = $gen->create_course(['fullname' => 'Done course', 'enablecompletion' => 1]);
        $gen->enrol_user($student->id, $course->id, 'student');
        $cms = [];
        foreach (['One', 'Two', 'Three'] as $name) {
            $cms[$name] = $gen->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }
        $gen->create_module('page', ['course' => $course->id, 'name' => 'Untracked']);
        $gen->create_module('page', [
            'course' => $course->id,
            'name' => 'Hidden',
            'visible' => 0,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $this->setUser($student);
        $completion = new \completion_info(get_course($course->id));
        $completion->update_state(get_fast_modinfo($course->id)->get_cm($cms['One']->cmid), COMPLETION_COMPLETE, $student->id);

        $answer = (new unfinished_activities())->handle(['course' => 'done'], $this->request($student));
        $this->assertSame(['Two', 'Three'], array_column($answer->rows, 'label'));
        $this->assertSame(['count' => 2, 'course' => 'Done course'], $answer->facts);
        $this->assertStringContainsString('/mod/page/view.php', $answer->rows[0]['url']);

        $answer = (new unfinished_activities())->handle([], $this->request($student, \context_course::instance($course->id)));
        $this->assertCount(2, $answer->rows);
    }

    /**
     * Completion disabled in the course gives the fallback.
     */
    public function test_unfinished_completion_off(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $course = $gen->create_course(['fullname' => 'Plain', 'enablecompletion' => 0]);
        $gen->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $answer = (new unfinished_activities())->handle(['course' => 'plain'], $this->request($student));
        $this->assertSame(get_string('completion_off', 'aiplacement_assistant'), $answer->fallback);
        $this->assertSame([], $answer->rows);
    }

    /**
     * Build a course with a visible 50/100 item and a hidden 100/100 item.
     *
     * @return array [student, course]
     */
    private function hidden_item_setup(): array {
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $course = $gen->create_course(['fullname' => 'Hidden item course']);
        $gen->enrol_user($student->id, $course->id, 'student');
        foreach ([['Shown', 0, 50], ['Secret', 1, 100]] as [$name, $hidden, $mark]) {
            $item = new \grade_item($gen->create_grade_item([
                'courseid' => $course->id,
                'itemtype' => 'manual',
                'itemname' => $name,
                'grademax' => 100,
                'hidden' => $hidden,
            ]), false);
            $item->update_final_grade($student->id, $mark);
        }
        grade_regrade_final_grades($course->id);
        $this->setUser($student);
        return [$student, $course];
    }

    /**
     * A total that includes hidden items is hidden from students by default.
     */
    public function test_my_grade_total_with_hidden_items_hidden(): void {
        $this->resetAfterTest();
        [$student, $course] = $this->hidden_item_setup();
        $answer = (new my_grade())->handle(['course' => 'Hidden item'], $this->request($student));
        $this->assertSame(get_string('grade_hidden', 'aiplacement_assistant'), $answer->rows[0]['value']);
        $this->assertStringContainsString('/grade/report/user/index.php?id=' . $course->id, $answer->rows[0]['url']);
    }

    /**
     * With the show-real-total setting the number is shown.
     */
    public function test_my_grade_total_with_hidden_items_real_total_setting(): void {
        $this->resetAfterTest();
        [$student, $course] = $this->hidden_item_setup();
        grade_set_setting($course->id, 'report_user_showtotalsifcontainhidden', GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN);
        $answer = (new my_grade())->handle(['course' => 'Hidden item'], $this->request($student));
        $this->assertNotSame(get_string('grade_hidden', 'aiplacement_assistant'), $answer->rows[0]['value']);
        $this->assertStringContainsString('150', $answer->rows[0]['value']);
    }

    /**
     * A calculated total that depends on a hidden item is hidden, as in the user report.
     *
     * Core puts such a total in "unknowngrades" rather than "altered" and blanks it.
     */
    public function test_my_grade_calculated_total_with_hidden_dependency(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $course = $gen->create_course(['fullname' => 'Calculated course']);
        $gen->enrol_user($student->id, $course->id, 'student');
        $secret = new \grade_item($gen->create_grade_item([
            'courseid' => $course->id,
            'itemtype' => 'manual',
            'itemname' => 'Secret',
            'grademax' => 100,
            'hidden' => 1,
        ]), false);
        $secret->update_final_grade($student->id, 77);
        $courseitem = \grade_item::fetch_course_item($course->id);
        $courseitem->set_calculation('=##gi' . $secret->id . '##');
        grade_regrade_final_grades($course->id);
        $this->setUser($student);

        $answer = (new my_grade())->handle(['course' => 'Calculated'], $this->request($student));
        $this->assertSame(get_string('grade_hidden', 'aiplacement_assistant'), $answer->rows[0]['value']);
        $this->assertStringNotContainsString('77', $answer->rows[0]['value']);
    }

    /**
     * Users who can view hidden grades see the real total.
     */
    public function test_my_grade_total_with_hidden_items_viewhidden(): void {
        $this->resetAfterTest();
        [$student, $course] = $this->hidden_item_setup();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/grade:viewhidden', CAP_ALLOW, $roleid, \context_course::instance($course->id));
        $this->getDataGenerator()->role_assign($roleid, $student->id, \context_course::instance($course->id));
        $answer = (new my_grade())->handle(['course' => 'Hidden item'], $this->request($student));
        $this->assertStringContainsString('150', $answer->rows[0]['value']);
    }

    /**
     * Create a student with an 80/100 grade in a course.
     *
     * @return array [user, course]
     */
    private function grade_setup(): array {
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $course = $gen->create_course(['fullname' => 'Grade course']);
        $gen->enrol_user($student->id, $course->id, 'student');
        $item = new \grade_item($gen->create_grade_item([
            'courseid' => $course->id,
            'itemtype' => 'manual',
            'itemname' => 'Manual',
            'grademax' => 100,
        ]), false);
        $item->update_final_grade($student->id, 80);
        grade_regrade_final_grades($course->id);
        $this->setUser($student);
        return [$student, $course];
    }
}
