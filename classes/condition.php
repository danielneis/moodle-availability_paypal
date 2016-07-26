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
 * Date condition.
 *
 * @package availability_paypal
 * @copyright 2015 Daniel Neis Araujo <danielneis@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_paypal;

defined('MOODLE_INTERNAL') || die();

/**
 * paypal condition.
 *
 * @package availability_paypal
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure from JSON decode
     * @throws \coding_exception If invalid data structure.
     */
    public function __construct($structure) {
        if (isset($structure->businessemail)) {
            $this->businessemail = $structure->businessemail;
        }
        if (isset($structure->currency)) {
            $this->currency = $structure->currency;
        }
        if (isset($structure->cost)) {
            $this->cost = $structure->cost;
        }
        if (isset($structure->itemname)) {
            $this->itemname = $structure->itemname;
        }
        if (isset($structure->itemnumber)) {
            $this->itemnumber = $structure->itemnumber;
        }
    }

    public function save() {
        $result = (object)array('type' => 'paypal');
        if ($this->businessemail) {
            $result->businessemail = $this->businessemail;
        }
        if ($this->currency) {
            $result->currency = $this->currency;
        }
        if ($this->cost) {
            $result->cost = $this->cost;
        }
        if ($this->itemname) {
            $result->itemname = $this->itemname;
        }
        if ($this->itemnumber) {
            $result->itemnumber = $this->itemnumber;
        }
        return $result;
    }

    /**
     * Returns a JSON object which corresponds to a condition of this type.
     *
     * Intended for unit testing, as normally the JSON values are constructed
     * by JavaScript code.
     *
     * @param string $businessemail The email of paypal to be credited
     * @param string $currency      The currency to charge the user
     * @param string $cost          The cost to charge the user
     * @return stdClass Object representing condition
     */
    public static function get_json($businessemail, $currency, $cost) {
        return (object)array('type' => 'paypal', 'businessemail' => $businessemail, 'currency' => $currency, 'cost' => $cost);
    }

    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        global $DB;
        // Should double-check with paypal everytime ?
        $context = $info->get_context();
        return $DB->record_exists('availability_paypal_tnx',
                                  array('userid' => $userid,
                                        'contextid' => $context->id,
                                        'payment_status' => 'Completed'));
    }

    public function get_description($full, $not, \core_availability\info $info) {
        return $this->get_either_description($not, false, $info);
    }
    /**
     * Shows the description using the different lang strings for the standalone
     * version or the full one.
     *
     * @param bool $not True if NOT is in force
     * @param bool $standalone True to use standalone lang strings
     * @param bool $info       Information about the availability condition and module context
     */
    protected function get_either_description($not, $standalone, $info) {
        $context = $info->get_context();
        $url = new \moodle_url('/availability/condition/paypal/view.php?contextid='.$context->id);
        return get_string('eitherdescription', 'availability_paypal', $url->out());
    }

    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name) {
        // Update the date, if restoring with changed date.
        $dateoffset = \core_availability\info::get_restore_date_offset($restoreid);
        if ($dateoffset) {
            $this->time += $dateoffset;
            return true;
        }
        return false;
    }

    protected function get_debug_string() {
        return gmdate('Y-m-d H:i:s');
    }
}
