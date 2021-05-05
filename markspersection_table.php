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

    /**
     * Redefine the parent function : lastest steps are always needed for this report.
     *
     * @return bool true (always true for this report).
     */
    protected function requires_latest_steps_loaded() {
        return true;
    }

    /**
     * Redefine the parent function : the last columns are sections marks.
     *
     * @param string $column a column name
     * @return int false if no, else a slot.
     */
    protected function is_latest_step_column($column) {
        if (preg_match('/^sectionmark([0-9]+)/', $column, $matches)) {
            return $matches[1];
        }
        return false;
    }

    /**
     * Redefine the parent function : This is the field needed for sorting by sections marks.
     *
     * @param int $slot the slot for the column we want.
     * @param string $alias the table alias for latest state information relating to that slot.
     */
    protected function get_required_latest_state_fields($slot, $alias) {
        // The name "marksection" comes from the subquery in question_attempt_latest_state_view.
        return "$alias.marksection AS sectionmark$slot";
    }

    /**
     * Redefine the parent function.
     * The only difference is the call from question_attempt_latest_state_view which point to the function in this class instead.
     *
     * @param int $slot the slot for the column we want.
     */
    protected function add_latest_state_join($slot) {
        $alias = 'qa' . $slot;

        $fields = $this->get_required_latest_state_fields($slot, $alias);
        if (!$fields) {
            return;
        }

        // This condition roughly filters the list of attempts to be considered.
        // It is only used in a subselect to help crappy databases (see MDL-30122)
        // therefore, it is better to use a very simple join, which may include
        // too many records, than to do a super-accurate join.
        $qubaids = new qubaid_join("{quiz_attempts} {$alias}quiza", "{$alias}quiza.uniqueid",
                "{$alias}quiza.quiz = :{$alias}quizid", array("{$alias}quizid" => $this->sql->params['quizid']));

        list($inlineview, $viewparams) = $this->question_attempt_latest_state_view($alias, $qubaids);

        $this->sql->fields .= ",\n$fields";
        $this->sql->from .= "\nLEFT JOIN $inlineview ON " .
                "$alias.questionusageid = quiza.uniqueid";
        $this->sql->params[$alias . 'slot'] = $slot;
        $this->sql->params = array_merge($this->sql->params, $viewparams);
    }

    /**
     * This is copied from question_attempt_latest_state_view from question_engine_data_mapper in question/engine/datalib.php.
     * Get a subquery that returns the latest step of every qa in some qubas.
     * We here bypass the subquery for every question by grouping them by section.
     *
     * @param string $alias alias to use for this inline-view.
     * @param qubaid_condition $qubaids restriction on which question_usages we
     *      are interested in. This is important for performance.
     * @return array with two elements, the SQL fragment and any params requried.
     */
    public function question_attempt_latest_state_view($alias, qubaid_condition $qubaids) {
        // Get the questions in the section we are ordering by. The questions ids are then inserted in the subquery.
        $sectionid = substr($alias, 2);
        $questions = $this->get_questions_in_section($sectionid);
        $questionsidsstr = implode(',', $questions);

        $where = $qubaids->where() . " AND {$alias}qa.questionid IN($questionsidsstr)";
        $whereparams = $qubaids->from_where_params();
        // The name "marksection" givent to the SUM function is the one to reuse in get_required_latest_state_fields.
        return array("(
                SELECT {$alias}qa.questionusageid,
                       SUM({$alias}qas.fraction * {$alias}qa.maxmark) AS marksection,
                       {$alias}qas.userid
                  FROM {$qubaids->from_question_attempts($alias . 'qa')}
                  JOIN {question_attempt_steps} {$alias}qas ON {$alias}qas.questionattemptid = {$alias}qa.id
                 WHERE {$where}
                 GROUP BY {$alias}qa.questionusageid, {$alias}qas.userid
            ) {$alias}", $whereparams);
    }

    /**
     * Get all the questions in the specified section.
     *
     * @param int $sectionid The id of the section.
     */
    public function get_questions_in_section($sectionid) {
        $cm = get_coursemodule_from_instance('quiz', $this->quiz->id, $this->quiz->course, false, MUST_EXIST);
        $quizobj = new \quiz($this->quiz, $cm, $this->quiz->course);
        $structure = $quizobj->get_structure();
        $slots = $structure->get_slots_in_section($sectionid);

        // Get all the questions in the slots of this section.
        $questionsinsection = array();
        foreach ($this->questions as $question) {
            if (in_array($question->slot, $slots)) {
                $questionsinsection[] = $question->id;
            }
        }
        return $questionsinsection;
    }
}
