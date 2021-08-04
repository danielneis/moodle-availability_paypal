<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace availability_paypal;

/**
 * Table shown at the transactions report.
 *
 * @package     availability_paypal
 * @copyright   2021 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transactions_table extends \table_sql {

    protected $coursenames = [];

    /**
     * Table setup.
     */
    public function __construct() {
        global $DB, $PAGE;

        parent::__construct('availability_paypal-transactions');

        $columns = ['id', 'item', 'fullname'];
        $headers = ['ID', 'item', ''];

        foreach (\core_user\fields::get_identity_fields($PAGE->context, false) as $field) {
            $columns[] = $field;
            $headers[] = \core_user\fields::get_display_name($field);
        }

        $columns = array_merge($columns, ['payment_status', 'pending_reason', 'txn_id', 'memo', 'timeupdated']);
        $headers = array_merge($headers, ['payment_status', 'pending_reason', 'txn_id', 'memo', get_string('time')]);

        $this->define_columns($columns);
        $this->define_headers($headers);

        $userfields = \core_user\fields::for_name()->with_identity($PAGE->context)->get_sql('u');

        $fields = 't.id, t.item_name, t.userid, t.contextid, t.sectionid, ' .
            't.payment_status, t.pending_reason,t.txn_id, t.memo, t.timeupdated' . $userfields->selects;

        $from = '{availability_paypal_tnx} t JOIN {user} u ON t.userid = u.id';

        $where = '1=1';

        $this->set_sql($fields, $from, $where);

        $this->define_baseurl($PAGE->url);

        $this->sortable(true, 'id', SORT_DESC);

        $this->coursenames = $DB->get_records('course', null, '', 'id,shortname');
    }

    /**
     * Format the paid item name.
     *
     * @param object $data
     */
    public function col_item($data) {
        global $PAGE;

        $context = \context::instance_by_id($data->contextid, IGNORE_MISSING);

        if (!$context) {
            return $data->item_name;
        }

        $url = $context->get_url();
        $coursename = '';
        $itemclass = '';

        if ($coursecontext = $context->get_course_context(false)) {
            $coursename = $this->coursenames[$coursecontext->instanceid]->shortname . ' / ';

            if ($coursecontext->instanceid != $PAGE->url->get_param('courseid')) {
                $itemclass = 'dimmed_text';
            }
        }

        if (!empty($data->sectionid)) {
            $url->param('sectionid', $data->sectionid);
        }

        return \html_writer::span($coursename . \html_writer::link($url, $data->item_name), $itemclass);
    }

    /**
     * Format the user name.
     *
     * @param object $data
     */
    public function col_fullname($data) {
        global $PAGE;

        if ($courseid = $PAGE->url->get_param('courseid')) {
            $profileurl = new \moodle_url('/user/view.php', ['id' => $data->userid, 'course' => $courseid]);

        } else {
            $profileurl = new \moodle_url('/user/profile.php', ['id' => $data->userid]);
        }

        return \html_writer::link($profileurl, fullname($data));
    }

    public function col_payment_status($data) {

        if ($data->payment_status === 'Completed') {
            $badge = \html_writer::span($data->payment_status, 'badge badge-success');

        } else if ($data->payment_status === 'Pending') {
            $badge = \html_writer::span($data->payment_status, 'badge badge-info');

        } else if ($data->payment_status === 'ToBeVerified') {
            $badge = \html_writer::span($data->payment_status, 'badge badge-warning');

        } else {
            $badge = \html_writer::span($data->payment_status, 'badge badge-danger');
        }

        return $badge;
    }

    /**
     * Format the time.
     *
     * @param object $data
     * @return string
     */
    public function col_timeupdated($data) {
        return userdate($data->timeupdated, get_string('strftimedatetime', 'langconfig'));
    }
}
