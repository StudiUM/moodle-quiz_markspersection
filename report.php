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
 * This file defines the quiz marks per section report class.
 *
 * @package   quiz_markspersection
 * @copyright 2021 Université de Montréal
 * @author    Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/overview/report.php');
require_once($CFG->dirroot . '/mod/quiz/report/markspersection/markspersection_form.php');
require_once($CFG->dirroot . '/mod/quiz/report/markspersection/markspersection_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/markspersection/markspersection_table.php');
use quiz_markspersection\quiz_attemptreport;

/**
 * Quiz report to help teachers display marks per section.
 *
 * @copyright 2021 Université de Montréal
 * @author    Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_markspersection_report extends quiz_overview_report {

    /**
     * Override this function to display the report.
     * A lot of stuff is duplicated from the parent function.
     *
     * @param stdClass $quiz this quiz.
     * @param stdClass $cm the course-module for this quiz.
     * @param stdClass $course the course we are in.
     */
    public function display($quiz, $cm, $course) {
        global $DB, $OUTPUT;

        list($currentgroup, $studentsjoins, $groupstudentsjoins, $allowedjoins) = $this->init(
                'markspersection', 'quiz_markspersection_settings_form', $quiz, $cm, $course);

        $options = new quiz_markspersection_options('markspersection', $quiz, $cm, $course);

        if ($fromform = $this->form->get_data()) {
            $options->process_settings_from_form($fromform);

        } else {
            $options->process_settings_from_params();
        }

        $this->form->set_data($options->get_initial_form_data());

        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                array('context' => context_course::instance($course->id)));
        $table = new quiz_markspersection_table($quiz, $this->context, $this->qmsubselect,
                $options, $groupstudentsjoins, $studentsjoins, $questions, $options->get_url());
        $filename = quiz_report_download_filename(get_string('markspersectionfilename', 'quiz_markspersection'),
                $courseshortname, $quiz->name);
        $table->is_downloading($options->download, $filename,
                $courseshortname . ' ' . format_string($quiz->name, true));
        if ($table->is_downloading()) {
            raise_memory_limit(MEMORY_EXTRA);
        }

        $this->hasgroupstudents = false;
        if (!empty($groupstudentsjoins->joins)) {
            $sql = "SELECT DISTINCT u.id
                      FROM {user} u
                    $groupstudentsjoins->joins
                     WHERE $groupstudentsjoins->wheres";
            $this->hasgroupstudents = $DB->record_exists_sql($sql, $groupstudentsjoins->params);
        }
        $hasstudents = false;
        if (!empty($studentsjoins->joins)) {
            $sql = "SELECT DISTINCT u.id
                    FROM {user} u
                    $studentsjoins->joins
                    WHERE $studentsjoins->wheres";
            $hasstudents = $DB->record_exists_sql($sql, $studentsjoins->params);
        }
        if ($options->attempts == self::ALL_WITH) {
            // This option is only available to users who can access all groups in
            // groups mode, so setting allowed to empty (which means all quiz attempts
            // are accessible, is not a security porblem.
            $allowedjoins = new \core\dml\sql_join();
        }

        $this->course = $course; // Hack to make this available in process_actions.
        $this->process_actions($quiz, $cm, $currentgroup, $groupstudentsjoins, $allowedjoins, $options->get_url());

        $hasquestions = quiz_has_questions($quiz->id);

        $quizobj = quiz::create($quiz->id);
        $sections = $quizobj->get_sections();
        if (count($sections) <= 1) {
            $this->print_header_and_tabs($cm, $course, $quiz);
            echo $this->quiz_no_sections_message($cm, $this->context);
            return true;
        }

        // Start output.
        if (!$table->is_downloading()) {
            // Only print headers if not asked to download data.
            $this->print_standard_header_and_messages($cm, $course, $quiz,
                    $options, $currentgroup, $hasquestions, $hasstudents);
            echo $OUTPUT->heading(get_string('markspersection', 'quiz_markspersection'), 3);
            // Print the display options.
            $this->form->display();
        }

        $hasstudents = $hasstudents && (!$currentgroup || $this->hasgroupstudents);
        if ($hasquestions && ($hasstudents || $options->attempts == self::ALL_WITH)) {
            // Construct the SQL.
            $table->setup_sql_queries($allowedjoins);

            if (!$table->is_downloading()) {
                // Print information on the grading method.
                if ($strattempthighlight = quiz_report_highlighting_grading_method(
                        $quiz, $this->qmsubselect, $options->onlygraded)) {
                    echo '<div class="quizattemptcounts">' . $strattempthighlight . '</div>';
                }
            }

            // Define table columns.
            $columns = array();
            $headers = array();

            if (!$table->is_downloading() && $options->checkboxcolumn) {
                $columnname = 'checkbox';
                $columns[] = $columnname;
                $headers[] = $table->checkbox_col_header($columnname);
            }

            $this->add_user_columns($table, $columns, $headers);
            $this->add_state_column($columns, $headers);
            $this->add_time_columns($columns, $headers);

            $this->add_grade_columns($quiz, $options->usercanseegrades, $columns, $headers, false);

            if (!$table->is_downloading() && has_capability('mod/quiz:regrade', $this->context) &&
                    $this->has_regraded_questions($table->sql->from, $table->sql->where, $table->sql->params)) {
                $columns[] = 'regraded';
                $headers[] = get_string('regrade', 'quiz_overview');
            }

            // Add columns for section grades.
            $quizobj->preload_questions();
            $quizobj->load_questions();
            $questions = $quizobj->get_questions();
            foreach ($sections as $isection => $section) {
                $columns[] = 'sectionmark' . $section->id;
                $header = empty($section->heading) ? get_string('sectionnoname', 'quiz_markspersection') : $section->heading;
                if (!$table->is_downloading()) {
                    $header .= '<br />';
                } else {
                    $header .= ' ';
                }

                // Calculate grades by section.
                $sum = 0;
                foreach ($questions as $question) {
                    $lastslot = isset($sections[$isection + 1]) ? $sections[$isection + 1]->firstslot : null;
                    if ($question->slot >= $section->firstslot && (is_null($lastslot) || $question->slot < $lastslot)) {
                        $sum += $question->maxmark;
                    }
                }
                $header .= '/' . quiz_format_grade($quiz, $sum);
                $headers[] = $header;
            }

            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $options, false);
            $table->set_attribute('class', 'generaltable generalbox grades');

            $table->out($options->pagesize, true);
        }
        return true;
    }

    /**
     * Display a message when there are no sections in the quiz.
     *
     * @param object $cm the course_module object.
     * @param object $context the quiz context.
     * @return string HTML to output.
     */
    protected function quiz_no_sections_message($cm, $context) {
        global $OUTPUT;
        $output = $OUTPUT->notification(get_string('nosections', 'quiz_markspersection'));
        if (has_capability('mod/quiz:manage', $context)) {
            $output .= $OUTPUT->single_button(new moodle_url('/mod/quiz/edit.php',
            array('cmid' => $cm->id)), get_string('editquiz', 'quiz'), 'get');
        }

        return $output;
    }
}
