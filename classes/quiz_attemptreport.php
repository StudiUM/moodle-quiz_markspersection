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
 * Class that extends quiz_attempt for particularities of markspersection report.
 *
 * @package   quiz_markspersection
 * @copyright 2021 Université de Montréal
 * @author    Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_markspersection;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Class that extends quiz_attempt for particularities of markspersection report.
 *
 * @copyright 2021 Université de Montréal
 * @author    Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_attemptreport extends \quiz_attempt {

    /** @var array Already calculated sections marks, stored in this variable for further use (to avoid recalculation). */
    private $sectionmarks = null;

    /**
     * Return sections marks.
     *
     * @return array data of sections marks.
     */
    public function get_sections_marks() {
        if (is_null($this->sectionmarks)) {
            $sections = $this->quizobj->get_sections();
            $sectionsmarks = [];
            // Initialize sections marks.
            foreach ($sections as $section) {
                $sectionsmarks[$section->id] = ['heading' => $section->heading,
                    'quizid' => $section->quizid, 'sumgrades' => null, 'summaxgrades' => 0];
            }

            // Calculate grades by section.
            foreach ($this->get_slots() as $slot) {
                $sectionid = $this->get_sectionid($slot);
                if (isset($sectionsmarks[$sectionid])) {
                    $mark = $this->quba->get_question_mark($slot);
                    if ($mark !== null) {
                        $sectionsmarks[$sectionid]['sumgrades'] += $mark;
                    }
                    $sectionsmarks[$sectionid]['summaxgrades'] += $this->quba->get_question_max_mark($slot);
                }
            }

            $this->sectionmarks = $sectionsmarks;
        }

        return $this->sectionmarks;
    }

    /**
     * Get the section id from the slot.
     *
     * @param int $slot the number used to identify this question within this attempt.
     * @return int the section id.
     */
    public function get_sectionid($slot) {
        return isset($this->slots[$slot]) ? $this->slots[$slot]->section->id : 0;
    }

    /**
     * Static function to create a new quiz_attempt object given an attemptid.
     *
     * @param int $attemptid the attempt id.
     * @return quiz_attempt the new quiz_attempt object
     */
    public static function create($attemptid) {
        return self::create_helper(array('id' => $attemptid));
    }

    /**
     * Used by {create()} and {create_from_usage_id()}.
     *
     * @param array $conditions passed to $DB->get_record('quiz_attempts', $conditions).
     * @return quiz_attemptreport the desired instance of this class.
     */
    protected static function create_helper($conditions) {
        global $DB;

        $attempt = $DB->get_record('quiz_attempts', $conditions, '*', MUST_EXIST);
        $quiz = \quiz_access_manager::load_quiz_and_settings($attempt->quiz);
        $course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

        // Update quiz with override information.
        $quiz = quiz_update_effective_access($quiz, $attempt->userid);

        return new quiz_attemptreport($attempt, $quiz, $cm, $course);
    }
}
