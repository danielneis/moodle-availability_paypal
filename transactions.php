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

/**
 * Shows all PayPal transactions and allows to manually validate them.
 *
 * @package     availability_paypal
 * @copyright   2021 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/tablelib.php');

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);

$PAGE->set_url(new moodle_url('/availability/condition/paypal/transactions.php', [
    'courseid' => $courseid,
    'perpage' => $perpage,
]));

$PAGE->navbar->add(get_string('transactionsreport', 'availability_paypal'), $PAGE->url);

require_login($courseid);
require_capability('availability/paypal:managetransactions', context_system::instance());

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('transactionsreport', 'availability_paypal'));

$table = new \availability_paypal\transactions_table();
$table->out($perpage, true);

$options = [];

foreach ([25, 50, 100, 500, TABLE_SHOW_ALL_PAGE_SIZE] as $showperpage) {
    $options[$showperpage] = get_string('showperpage', 'core', $showperpage);
}

if ($table->totalrows) {
    echo html_writer::start_div('my-3');
    echo $OUTPUT->single_select($PAGE->url, 'perpage', $options, $perpage);
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
