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
 * Tests for the quiz marks per section report.
 *
 * @package   quiz_markspersection
 * @copyright 2021 Université de Montréal
 * @author    Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/report/default.php');
require_once($CFG->dirroot . '/mod/quiz/report/markspersection/report.php');

use \quiz_markspersection\quiz_attemptreport;

/**
 * Tests for the quiz marks per section report.
 *
 * @copyright 2021 Université de Montréal
 * @author    Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_markspersection_report_testcase extends advanced_testcase {
    /**
     * Test get_sections_marks function
     */
    public function test_get_sections_marks() {
        $this->resetAfterTest();

        // Create a course.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Create 2 quizzes : one without sections and one with sections.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');

        $quiz1 = $quizgenerator->create_instance(array('course' => $course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 2, 'preferredbehaviour' => 'immediatefeedback'));
        $quiz2 = $quizgenerator->create_instance(array('course' => $course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 2, 'preferredbehaviour' => 'immediatefeedback'));

        $quizobj1 = quiz::create($quiz1->id, $user->id);
        $quizobj2 = quiz::create($quiz2->id, $user->id);

        $quba1 = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj1->get_context());
        $quba1->set_preferred_behaviour($quizobj1->get_quiz()->preferredbehaviour);
        $cm1 = get_coursemodule_from_instance('quiz', $quiz1->id, $course->id);

        $quba2 = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj2->get_context());
        $quba2->set_preferred_behaviour($quizobj2->get_quiz()->preferredbehaviour);
        $cm2 = get_coursemodule_from_instance('quiz', $quiz2->id, $course->id);

        // Create questions and add them to both quizzes.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $question1 = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question1->id, $quiz1, 1, 2.5);
        quiz_add_quiz_question($question1->id, $quiz2, 1, 2.5);

        $question2 = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question2->id, $quiz1, 1, 1.5);
        quiz_add_quiz_question($question2->id, $quiz2, 1, 1.5);

        $question3 = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question3->id, $quiz1, 2, 1);
        quiz_add_quiz_question($question3->id, $quiz2, 2, 1);

        $question4 = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question4->id, $quiz1, 2, 1.75);
        quiz_add_quiz_question($question4->id, $quiz2, 2, 1.75);

        $question5 = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question5->id, $quiz1, 3, 2);
        quiz_add_quiz_question($question5->id, $quiz2, 3, 2);

        $question6 = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question6->id, $quiz1, 3, 3);
        quiz_add_quiz_question($question6->id, $quiz2, 3, 3);

        // Case 1 : When there are no sections in the quiz.
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj1, 1, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj1, $quba1, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj1, $quba1, $attempt);

        // Process some responses from the student.
        $attemptobj = quiz_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => 0),
            2 => array('answer' => 0),
            3 => array('answer' => 1),
            4 => array('answer' => 0),
            5 => array('answer' => 1),
            6 => array('answer' => 1));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);
        $attemptobj->process_finish($timenow, false);

        // Check the data for the report - there is at least one section.
        $quizattemptsreport = quiz_attemptreport::create($attemptobj->get_attemptid());
        $sectionsmarks = $quizattemptsreport->get_sections_marks();
        $sectionsmarks = array_values($sectionsmarks);
        $this->assertCount(1, $sectionsmarks);
        $this->assertEquals('', $sectionsmarks[0]['heading']);
        $this->assertEquals(6, $sectionsmarks[0]['sumgrades']);
        $this->assertEquals(11.75, $sectionsmarks[0]['summaxgrades']);

        // Case 2 : When there are sections in the quiz.
        // Create the structure and sections in the quiz.
        $structure = \mod_quiz\structure::create_for_quiz($quizobj2);
        // Default section.
        $sections = $structure->get_sections();
        $firstsection = reset($sections);
        $structure->set_section_heading($firstsection->id, 'Section 1');

        // Section 2.
        $structure->add_section_heading(2, 'Section 2');

        // Section 3.
        $structure->add_section_heading(3, 'Section 3');

        $timenow = time();
        $attempt = quiz_create_attempt($quizobj2, 1, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj2, $quba2, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj2, $quba2, $attempt);

        // Process some responses from the student.
        $attemptobj = quiz_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => 0),
            2 => array('answer' => 0),
            3 => array('answer' => 1),
            4 => array('answer' => 0),
            5 => array('answer' => 1),
            6 => array('answer' => 1));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);
        $attemptobj->process_finish($timenow, false);

        // Check the data for the report.
        $quizattemptsreport = new quiz_markspersection_report();

        $quizattemptsreport = quiz_attemptreport::create($attemptobj->get_attemptid());
        $sectionsmarks = $quizattemptsreport->get_sections_marks();
        $sectionsmarks = array_values($sectionsmarks);
        $this->assertCount(3, $sectionsmarks);
        $this->assertEquals('Section 1', $sectionsmarks[0]['heading']);
        $this->assertEquals('Section 2', $sectionsmarks[1]['heading']);
        $this->assertEquals('Section 3', $sectionsmarks[2]['heading']);
        $this->assertEquals(0, $sectionsmarks[0]['sumgrades']);
        $this->assertEquals(1, $sectionsmarks[1]['sumgrades']);
        $this->assertEquals(5, $sectionsmarks[2]['sumgrades']);
        $this->assertEquals(4, $sectionsmarks[0]['summaxgrades']);
        $this->assertEquals(2.75, $sectionsmarks[1]['summaxgrades']);
        $this->assertEquals(5, $sectionsmarks[2]['summaxgrades']);
    }
}
