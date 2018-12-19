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
 * @copyright 2015 Daniel Neis Araujo <danielneis@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_paypal;

defined('MOODLE_INTERNAL') || die();

/**
 * Front-end class.
 *
 * @package availability_paypal
 * @copyright 2015 Daniel Neis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {

    /**
     * Return list if string indexes used by javascript
     *
     * @return array
     */
    protected function get_javascript_strings() {
        return array('ajaxerror', 'businessemail', 'currency', 'cost', 'itemname', 'itemnumber');
    }

    /**
     * Return true always - should be if user can add the condition
     *
     * @param stdClass $course
     * @param \cm_info $cm
     * @param \section_info $section
     * @return bool
     */
    protected function allow_add($course, \cm_info $cm = null, \section_info $section = null) {
        return true;
    }

    /**
     * Gets additional parameters for the plugin's initInner function.
     *
     * Default returns no parameters.
     *
     * @param \stdClass $course Course object
     * @param \cm_info $cm Course-module currently being edited (null if none)
     * @param \section_info $section Section currently being edited (null if none)
     * @return array Array of parameters for the JavaScript function
     */
    protected function get_javascript_init_params($course, \cm_info $cm = null, \section_info $section = null) {
        return array(\get_string_manager()->get_list_of_currencies());
    }
}
