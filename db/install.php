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
 * Post-install script for the quiz marks per section report.
 *
 * @package   quiz_markspersection
 * @copyright 2021 Université de Montréal
 * @author    Issam Taboubi <issam.taboubi@umontre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Post-install script
 */
function xmldb_quiz_markspersection_install() {
    global $DB;

    $record = new stdClass();
    $record->name         = 'markspersection';
    $record->displayorder = '7000';

    $DB->insert_record('quiz_reports', $record);
}
