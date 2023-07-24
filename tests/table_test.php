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
 * Tests for the table of quiz marks per section report.
 *
 * @package   quiz_markspersection
 * @copyright 2021 Université de Montréal
 * @author    Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_markspersection;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/markspersection/report.php');

/**
 * Tests for the table of quiz marks per section report.
 *
 * @copyright 2021 Université de Montréal
 * @author    Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \quiz_markspersection\quiz_attemptreport
 */
class table_test extends \advanced_testcase {
    /**
     * Test get_questions_in_section function
     */
    public function test_get_questions_in_section() {
        $this->resetAfterTest();

        // Create a course.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Create a quiz.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz1 = $quizgenerator->create_instance(array('course' => $course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 2, 'preferredbehaviour' => 'immediatefeedback'));

        $quizobj1 = \quiz::create($quiz1->id, $user->id);
        $context1 = $quizobj1->get_context();

        $quba1 = \question_engine::make_questions_usage_by_activity('mod_quiz', $context1);
        $quba1->set_preferred_behaviour($quizobj1->get_quiz()->preferredbehaviour);
        $cm1 = get_coursemodule_from_instance('quiz', $quiz1->id, $course->id);

        // Create questions and add them to the quiz.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $question1 = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        $question1->slot = 1;
        quiz_add_quiz_question($question1->id, $quiz1, 1, 2.5);

        $question2 = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        $question2->slot = 2;
        quiz_add_quiz_question($question2->id, $quiz1, 1, 1.5);

        $question3 = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        $question3->slot = 3;
        quiz_add_quiz_question($question3->id, $quiz1, 2, 1);

        $question4 = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        $question4->slot = 4;
        quiz_add_quiz_question($question4->id, $quiz1, 2, 1.75);

        $question5 = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        $question5->slot = 5;
        quiz_add_quiz_question($question5->id, $quiz1, 3, 2);

        $question6 = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        $question6->slot = 6;
        quiz_add_quiz_question($question6->id, $quiz1, 3, 3);

        // Create the structure and sections in the quiz.
        $structure = \mod_quiz\structure::create_for_quiz($quizobj1);
        // Default section.
        $sections = $structure->get_sections();
        $firstsection = reset($sections);
        $structure->set_section_heading($firstsection->id, 'Section 1');

        // Section 2.
        $structure->add_section_heading(2, 'Section 2');

        // Section 3.
        $structure->add_section_heading(3, 'Section 3');

        // Now do a minimal set-up of the table class.
        $qmsubselect = quiz_report_qm_filter_select($quiz1);
        $studentsjoins = get_enrolled_with_capabilities_join($context1, '',
                array('mod/quiz:attempt', 'mod/quiz:reviewmyattempts'));
        $empty = new \core\dml\sql_join();

        // Set the options.
        $reportoptions = new \quiz_markspersection_options('markspersection', $quiz1, $cm1, null);
        $reportoptions->attempts = \quiz_attempts_report::ENROLLED_ALL;
        $reportoptions->onlygraded = true;
        $reportoptions->states = array(\quiz_attempt::IN_PROGRESS, \quiz_attempt::OVERDUE, \quiz_attempt::FINISHED);

        // Initialise the table class.
        $table = new \quiz_markspersection_table($quiz1, $quizobj1->get_context(), $qmsubselect, $reportoptions,
            $empty, $studentsjoins,
            array(1 => $question1, 2 => $question2, 3 => $question3, 4 => $question4, 5 => $question5, 6 => $question6),
            null);

        // Assert the get_questions_in_section function.
        $sections = $quizobj1->get_sections();
        $questions = $table->get_questions_in_section($sections[0]->id);
        $this->assertContains($question1->id, $questions);
        $this->assertContains($question2->id, $questions);
        $questions = $table->get_questions_in_section($sections[1]->id);
        $this->assertContains($question3->id, $questions);
        $this->assertContains($question4->id, $questions);
        $questions = $table->get_questions_in_section($sections[2]->id);
        $this->assertContains($question5->id, $questions);
        $this->assertContains($question6->id, $questions);
    }
}
