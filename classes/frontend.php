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
 * Front-end class.
 *
 * @package availability_paypal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_paypal;

defined('MOODLE_INTERNAL') || die();

/**
 * Front-end class.
 *
 * @package availability_paypal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {

    protected function get_javascript_strings() {
        return array('ajaxerror');
    }

    protected function allow_add($course, \cm_info $cm = null,
            \section_info $section = null) {
        return true;
    }

    protected function get_javascript_init_params($course, \cm_info $cm = null,
            \section_info $section = null) {

        $context = \context_course::instance($course->id);
        return array((object)array('name' => 'testing'));
    }
}
