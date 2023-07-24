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
 * Class to store the options for a quiz_markspersection_report.
 *
 * @package   quiz_markspersection
 * @copyright 2021 Université de Montréal
 * @author    Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class to store the options for a quiz_markspersection_report.
 *
 * @copyright 2021 Université de Montréal
 * @author    Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_markspersection_options extends mod_quiz_attempts_report_options {

    /** @var bool whether to show only attempt that need regrading.
     * This variable is necessary to stay compatible with quiz_overview_options even if we do not extend it. */
    public $onlyregraded = false;

    /** @var bool whether to show marks for each question (slot).
     * This variable is necessary to stay compatible with quiz_overview_options even if we do not extend it. */
    public $slotmarks = false;

    /**
     * Show the checkbox column if relevant.
     */
    public function resolve_dependencies() {
        parent::resolve_dependencies();

        // We only want to show the checkbox to delete attempts
        // if the user has permissions and if the report mode is showing attempts.
        $this->checkboxcolumn = has_any_capability(
                array('mod/quiz:deleteattempts'), context_module::instance($this->cm->id))
                && ($this->attempts != quiz_attempts_report::ENROLLED_WITHOUT);
    }
}
