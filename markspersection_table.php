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

    /** @var array Array of attemps section marks. */
    private $attemptsectionsmarks = array();

    /** @var Array Array of attempts ids in the report. */
    private $attemptsids = [];

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
        $sumgrades = $marks[$section]['sumgrades'];
        if ($sumgrades !== null) {
            $sumgrades = quiz_format_question_grade($this->quiz, $sumgrades);
        } else {
            return '-';
        }
        return $sumgrades;
    }

    /**
     * Calculate sum of sections marks.
     *
     * @param array $attemptsids array of attempts ids.
     */
    public function calculate_sections_sum($attemptsids) {
        foreach ($attemptsids as $attemptid) {
            if (!(isset($this->attemptreports[$attemptid]))) {
                $this->attemptreports[$attemptid] = quiz_attemptreport::create($attemptid);
            }
            $attemptobj = $this->attemptreports[$attemptid];
            $marks = $attemptobj->get_sections_marks();
            foreach ($marks as $key => $section) {
                $sumgrades = $marks[$key]['sumgrades'];
                $colname = 'sectionmark' . $key;

                // Calculate the sum of section marks for each attempt.
                if (!isset($this->attemptsectionsmarks[$colname]) && $sumgrades !== null) {
                    $this->attemptsectionsmarks[$colname] = $sumgrades;
                } else if ($sumgrades !== null) {
                    $this->attemptsectionsmarks[$colname] += $sumgrades;
                }
                if ($sumgrades !== null) {
                    $this->attemptsids[$colname][] = $attemptid;
                }
            }
        }
    }

    /**
     * Calculate the average overall and section marks for a set of attempts at the quiz.
     *
     * @param string $label the title to use for this row.
     * @param \core\dml\sql_join $usersjoins to indicate a set of users.
     * @return array of table cells that make up the average row.
     */
    public function compute_average_row($label, \core\dml\sql_join $usersjoins) {
        global $DB;

        list($fields, $from, $where, $params) = $this->base_sql($usersjoins);
        $record = $DB->get_record_sql("
                SELECT AVG(quizaouter.sumgrades) AS grade, COUNT(quizaouter.sumgrades) AS numaveraged
                  FROM {quiz_attempts} quizaouter
                  JOIN (
                       SELECT DISTINCT quiza.id
                         FROM $from
                        WHERE $where
                       ) relevant_attempt_ids ON quizaouter.id = relevant_attempt_ids.id
                ", $params);
        $record->grade = quiz_rescale_grade($record->grade, $this->quiz, false);
        if ($this->is_downloading()) {
            $namekey = 'lastname';
        } else {
            $namekey = 'fullname';
        }
        $averagerow = array(
            $namekey       => $label,
            'sumgrades'    => $this->format_average($record),
            'feedbacktext' => strip_tags(quiz_report_feedback_for_grade(
                                         $record->grade, $this->quiz->id, $this->context))
        );
        $qubaids = new qubaid_join("{quiz_attempts} quizaouter
                JOIN (
                    SELECT DISTINCT quiza.id
                        FROM $from
                    WHERE $where
                    ) relevant_attempt_ids ON quizaouter.id = relevant_attempt_ids.id",
                'quizaouter.uniqueid', '1 = 1', $params);
        $sql = "
            SELECT  DISTINCT quizaouter.id
              FROM {$qubaids->from_question_attempts('qa')}
             WHERE {$qubaids->where()}";
        $attemptsids = $DB->get_records_sql($sql, $qubaids->from_where_params());

        // Calcute the average of sections marks.
        $this->calculate_sections_sum(array_keys($attemptsids));
        if (!empty($this->attemptsectionsmarks) && !empty($this->attemptsids)) {
            array_walk_recursive($this->attemptsectionsmarks, function(&$item, $key) {
                if (is_numeric($item)) {
                    $item = $item / count($this->attemptsids[$key]);
                    $record = new stdClass();
                    $record->grade = $item;
                    $record->numaveraged = count($this->attemptsids[$key]);
                    $item = $this->format_average($record);
                }
            });
            $averagerow += $this->attemptsectionsmarks;
        }
        return $averagerow;
    }

    /**
     * Redefine the parent function with only relevant information (quiz_overview_table has too many).
     */
    protected function submit_buttons() {
        quiz_attempts_report_table::submit_buttons();
    }
}