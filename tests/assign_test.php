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

/**
 * Base class for unit tests for mod_assign.
 *
 * @package    mod_assign
 * @category   phpunit
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

use \theme_snap\local;
use \theme_snap\activity;

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/base_test.php');

/**
 * Unit tests for theme snap that rely on mod/assign present in course.
 *
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class theme_snap_assign_test extends mod_assign_base_testcase {

    public function test_assign_reopened_and_resubmitted() {
        $this->setUser($this->editingteachers[0]);
        $this->create_instance();
        $assign = $this->create_instance(array('duedate' => time(),
                                               'attemptreopenmethod' => ASSIGN_ATTEMPT_REOPEN_METHOD_MANUAL,
                                               'maxattempts' => 3,
                                               'submissiondrafts' => 1,
                                               'assignsubmission_onlinetext_enabled' => 1));

        // Add a submission.
        $this->setUser($this->students[0]);
        $submission = $assign->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_HTML);
        $plugin = $assign->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking.
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $assign->testable_update_submission($submission, $this->students[0]->id, true, false);

        // Mark the submission.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $assign->testable_apply_grade_to_user($data, $this->students[0]->id, 0);
        // TODO remove this next line when the above is fixed  to stop triggering debug messages.
        $this->resetDebugging();

        // This is required so that the submissions timemodified > the grade timemodified.
        sleep(2);

        // Edit the submission again.
        $this->setUser($this->students[0]);
        $submission = $assign->get_user_submission($this->students[0]->id, true);
        $assign->testable_update_submission($submission, $this->students[0]->id, true, false);

        // This is required so that the submissions timemodified > the grade timemodified.
        sleep(2);

        // Allow the student another attempt.
        $this->teachers[0]->ignoresesskey = true;
        $this->setUser($this->teachers[0]);
        $assign->testable_process_add_attempt($this->students[0]->id);

        // Add another submission.
        $this->setUser($this->students[0]);
        $submission = $assign->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text 2',
                                         'format' => FORMAT_HTML);
        $plugin = $assign->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking (again).
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $assign->testable_update_submission($submission, $this->students[0]->id, true, false);

        $modinfo = get_fast_modinfo($this->course);
        $assigncm = $modinfo->instances['assign'][$assign->get_instance()->id];
        $expnumsubmissions = 1;
        $expnumrequiregrading = 1;

        $this->setUser($this->editingteachers[0]);
        $actual = activity::assign_meta($assigncm);
        $this->assertSame($actual->numsubmissions, $expnumsubmissions);
        $this->assertSame($actual->numrequiregrading, $expnumrequiregrading);
        $this->assertTrue($actual->isteacher);

        $actual = activity::assign_meta($assigncm);
        $this->assertSame($actual->numsubmissions, $expnumsubmissions);
        $this->assertSame($actual->numrequiregrading, $expnumrequiregrading);
        $this->assertTrue($actual->isteacher);

        $this->setUser($this->teachers[0]);
        $actual = activity::assign_meta($assigncm);
        $this->assertSame($actual->numsubmissions, $expnumsubmissions);
        $this->assertSame($actual->numrequiregrading, $expnumrequiregrading);
        $this->assertTrue($actual->isteacher);

        $this->setUser($this->students[0]);
        $actual = activity::assign_meta($assigncm);
        $this->assertFalse($actual->isteacher);
    }

    public function test_assign_upcoming_deadlines() {
        $this->setUser($this->editingteachers[0]);
        $this->create_instance(['duedate' => time()]);

        $deadlines = local::upcoming_deadlines($this->editingteachers[0]->id);
        $this->assertCount(1, $deadlines);

        $this->setUser($this->students[0]);
        $deadlines = local::upcoming_deadlines($this->students[0]->id);
        $this->assertCount(1, $deadlines);

        $this->setUser($this->teachers[0]);
        $deadlines = local::upcoming_deadlines($this->teachers[0]->id);
        $this->assertCount(1, $deadlines);

        $this->setUser($this->editingteachers[0]);
        $this->create_instance(['duedate' => time() + 3 * DAYSECS]);

        $deadlines = local::upcoming_deadlines($this->editingteachers[0]->id);
        $this->assertCount(2, $deadlines);

        $this->setUser($this->students[0]);
        $deadlines = local::upcoming_deadlines($this->students[0]->id);
        $this->assertCount(2, $deadlines);

        $this->setUser($this->teachers[0]);
        $deadlines = local::upcoming_deadlines($this->teachers[0]->id);
        $this->assertCount(2, $deadlines);

        /* TODO create non visible deadline.
        $this->setUser($this->editingteachers[0]);
        $this->create_instance(['duedate' => time() + 3 * DAYSECSe]);
        $deadlines = local::upcoming_deadlines($this->editingteachers[0]->id);
        $this->assertCount(3, $deadlines);

        $this->setUser($this->students[0]);
        $deadlines = local::upcoming_deadlines($this->students[0]->id);
        $this->assertCount(2, $deadlines);

        $this->setUser($this->teachers[0]);
        $deadlines = local::upcoming_deadlines($this->teachers[0]->id);
        $this->assertCount(3, $deadlines);
        */

        $this->create_instance(['duedate' => time() + 4 * DAYSECS]);
        $this->create_instance(['duedate' => time() + 4 * DAYSECS]);
        $max = 2;
        $this->setUser($this->students[0]);
        $deadlines = local::upcoming_deadlines($this->students[0]->id, $max);
        $this->assertCount(2, $deadlines);
    }

    public function test_participant_count() {
        $courseid = $this->course->id;
        $actual = local::course_participant_count($courseid);
        $expected = count($this->students);
        $this->assertSame($expected, $actual);

        $this->create_extra_users();
        $actual = local::course_participant_count($courseid);
        $expected = count($this->students);
        $this->assertSame($expected, $actual);
    }

    public function test_no_course_completion_progress() {
        $actual = local::course_completion_progress($this->course);
        $this->assertNull($actual);

        $this->create_extra_users();
        $this->setUser($this->extrasuspendedstudents[0]);
        $actual = local::course_completion_progress($this->course);
        $this->assertNull($actual);

        $this->setUser($this->students[0]);
        $actual = local::course_completion_progress($this->course);
        $this->assertInstanceOf('stdClass', $actual);
    }

    public function test_course_feedback() {
        $actual = local::course_feedback($this->course);
        $this->assertObjectHasAttribute('skipgrade', $actual);

        $this->setUser($this->students[0]);
        $actual = local::course_feedback($this->course);
        $this->assertObjectHasAttribute('feedbackhtml', $actual);
        $this->assertSame('', $actual->feedbackhtml);

        $assign = $this->create_one_ungraded_submission();
        $this->grade_assignment($assign);

        $this->setUser($this->students[0]);
        $actual = local::course_feedback($this->course);
        $this->assertObjectHasAttribute('feedbackhtml', $actual);
        $this->assertNotSame('', $actual->feedbackhtml);

        $this->create_extra_users();
        $this->setUser($this->extrasuspendedstudents[0]);
        $actual = local::course_feedback($this->course);
        $this->assertObjectHasAttribute('skipgrade', $actual);
        $this->assertContains('not enrolled', $actual->skipgrade);

        $this->setUser($this->students[0]);
        $this->course->showgrades = 0;
        $actual = local::course_feedback($this->course);
        $this->assertObjectHasAttribute('skipgrade', $actual);
        $this->assertContains('set up to not show gradebook to students', $actual->skipgrade);

    }

    public function test_no_course_image() {
        $actual = local::course_coverimage_url($this->course->id);
        $this->assertFalse($actual);
    }

    private function create_one_ungraded_submission() {
        $this->setUser($this->editingteachers[0]);
        $assign = $this->create_instance(['assignsubmission_onlinetext_enabled' => 1]);

        // Add a submission.
        $this->setUser($this->students[0]);
        $submission = $assign->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_HTML);
        $plugin = $assign->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $assign->testable_update_submission($submission, $this->students[0]->id, true, false);
        return $assign;
    }

    private function grade_assignment($assign) {
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $assign->testable_apply_grade_to_user($data, $this->students[0]->id, 0);
        // TODO remove this next line when the above is fixed  to stop triggering debug messages.
        $this->resetDebugging();
    }

    public function test_assign_ungraded() {
        $sixmonthsago = time() - YEARSECS / 2;

        $actual = activity::assign_ungraded([], $sixmonthsago);
        $this->assertCount(0, $actual);

        $actual = activity::assign_ungraded([$this->course->id], $sixmonthsago);
        $this->assertCount(0, $actual);

        $this->create_one_ungraded_submission();

        $actual = activity::assign_ungraded([$this->course->id], $sixmonthsago);
        $this->assertCount(1, $actual);
    }

    public function test_quiz_ungraded() {
        $sixmonthsago = time() - YEARSECS / 2;

        $actual = activity::quiz_ungraded([], $sixmonthsago);
        $this->assertCount(0, $actual);

        $actual = activity::quiz_ungraded([$this->course->id], $sixmonthsago);
        $this->assertCount(0, $actual);

        // TODO need a test with actually generated quizzes.
    }

    public function test_events_graded() {
        $this->setUser($this->editingteachers[0]);
        $this->create_instance();
        $assign = $this->create_instance(array('duedate' => time(),
                                               'submissiondrafts' => 1,
                                               'assignsubmission_onlinetext_enabled' => 1));

        // Add a submission.
        $this->setUser($this->students[0]);
        $submission = $assign->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_HTML);
        $plugin = $assign->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking.
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $assign->testable_update_submission($submission, $this->students[0]->id, true, false);

        // Mark the submission.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $assign->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // TODO remove this next line when the above is fixed  to stop triggering debug messages.
        $this->resetDebugging();

        $this->setUser($this->students[0]);
        $actual = activity::events_graded();
        $this->assertCount(1, $actual);

        $this->setUser($this->students[1]);
        $actual = activity::events_graded();
        $this->assertCount(0, $actual);
    }

    public function test_all_ungraded() {

        $sixmonthsago = time() - YEARSECS / 2;
        $expected = 0;

        $this->setUser($this->teachers[0]);
        $actual = local::all_ungraded($this->teachers[0]->id, $sixmonthsago);
        $this->assertcount($expected, $actual);

        $this->setUser($this->teachers[1]);
        $actual = local::all_ungraded($this->teachers[1]->id, $sixmonthsago);
        $this->assertcount($expected, $actual);

        $this->setUser($this->editingteachers[0]);
        $actual = local::all_ungraded($this->editingteachers[0]->id, $sixmonthsago);
        $this->assertcount($expected, $actual);

        $this->create_one_ungraded_submission();
        $expected = 1;

        $this->setUser($this->teachers[0]);
        $actual = local::all_ungraded($this->teachers[0]->id, $sixmonthsago);
        $this->assertcount($expected, $actual);

        $this->setUser($this->teachers[1]);
        $actual = local::all_ungraded($this->teachers[1]->id, $sixmonthsago);
        $this->assertcount($expected, $actual);

        $this->setUser($this->editingteachers[0]);
        $actual = local::all_ungraded($this->editingteachers[0]->id, $sixmonthsago);
        $this->assertcount($expected, $actual);
    }

    public function test_courseinfo_empty() {
        $actual = local::courseinfo([]);
        $this->assertCount(0, $actual);

        // Current user not enrolled in this course.
        $actual = local::courseinfo([$this->course->id]);
        $this->assertCount(0, $actual);

        // Current user enrolled but inactive in this course.
        $this->create_extra_users();
        $this->setUser($this->extrasuspendedstudents[0]);
        $actual = local::courseinfo([$this->course->id]);
        $this->assertCount(0, $actual);
    }

    public function test_courseinfo_student() {
        $this->setUser($this->students[0]);
        $actual = local::courseinfo([$this->course->id]);
        $this->assertCount(1, $actual);
    }

    public function test_courseinfo_teacher() {
        $this->setUser($this->teachers[0]);
        $actual = local::courseinfo([$this->course->id]);
        $this->assertCount(1, $actual);
    }
}
