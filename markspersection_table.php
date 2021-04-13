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
 * This file defines the quiz marks per section table.
 *
 * @package   quiz_markspersection
 * @copyright 2021 Université de Montréal
 * @author    Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_table.php');
use quiz_markspersection\quiz_attemptreport;

/**
 * This is a table subclass for displaying the quiz marks per section report.
 *
 * @copyright 2021 Université de Montréal
 * @author    Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_markspersection_table extends quiz_overview_table {

    /** @var array Array of quiz_attemptreport (kept for further usage, to avoid unnecessary initialisations and calculations). */
    private $attemptreports = array();

    /**
     * Constructor
     * @param object $quiz
     * @param context $context
     * @param string $qmsubselect
     * @param quiz_overview_options $options
     * @param \core\dml\sql_join $groupstudentsjoins
     * @param \core\dml\sql_join $studentsjoins
     * @param array $questions
     * @param moodle_url $reporturl
     */
    public function __construct($quiz, $context, $qmsubselect,
             $options, \core\dml\sql_join $groupstudentsjoins,
            \core\dml\sql_join $studentsjoins, $questions, $reporturl) {
            quiz_attempts_report_table::__construct('mod-quiz-report-markspersection-report', $quiz , $context,
                $qmsubselect, $options, $groupstudentsjoins, $studentsjoins, $questions, $reporturl);
    }

    /**
     * Return the cell content for a specific column.
     *
     * @param string $colname the name of the column.
     * @param object $attempt the row of data - see the SQL in display() in
     * mod/quiz/report/overview/report.php to see what fields are present,
     * and what they are called.
     * @return string the contents of the cell.
     */
    public function other_cols($colname, $attempt) {
        // Only columns named sectionmarkAAA (where AAA id an id) will be filled here.
        if (!preg_match('/^sectionmark(\d+)$/', $colname, $matches)) {
            return parent::other_cols($colname, $attempt);
        }

        if (is_null($attempt) || empty($attempt->attempt)) {
            return '-';
        }

        if (!(isset($this->attemptreports[$attempt->attempt]))) {
            $this->attemptreports[$attempt->attempt] = quiz_attemptreport::create($attempt->attempt);
        }

        $attemptobj = $this->attemptreports[$attempt->attempt];
        $marks = $attemptobj->get_sections_marks();

        $section = $matches[1];
        return $marks[$section]['sumgrades'];
    }

    /**
     * Redefine the parent function with only relevant information (quiz_overview_table has too many).
     */
    public function build_table() {
        global $DB;

        if (!$this->rawdata) {
            return;
        }

        $this->strtimeformat = str_replace(',', ' ', get_string('strftimedatetime'));
        quiz_attempts_report_table::build_table();
    }

    /**
     * Redefine the parent function with only relevant information (quiz_overview_table has too many).
     */
    protected function submit_buttons() {
        quiz_attempts_report_table::submit_buttons();
    }
}
