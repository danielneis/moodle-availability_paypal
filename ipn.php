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
 * Listens for Instant Payment Notification from PayPal
 *
 * This script waits for Payment notification from PayPal,
 * then double checks that data by sending it back to PayPal.
 * If PayPal verifies this then sets the activity as completed.
 *
 * @package    availability_paypal
 * @copyright  2010 Eugene Venter
 * @copyright  2015 Daniel Neis
 * @author     Eugene Venter - based on code by others
 * @author     Daniel Neis - based on code by others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require("../../../config.php");
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir . '/filelib.php');

// PayPal does not like when we return error messages here,
// the custom handler just logs exceptions and stops.
set_exception_handler('availability_paypal_ipn_exception_handler');

// Keep out casual intruders.
if (empty($_POST) or !empty($_GET)) {
    print_error("Sorry, you can not use the script that way.");
}


// Read all the data from PayPal and get it ready for later;
// we expect only valid UTF-8 encoding, it is the responsibility
// of user to set it up properly in PayPal business account,
// it is documented in docs wiki.
$req = 'cmd=_notify-validate';

foreach ($_POST as $key => $value) {
        $req .= "&$key=".urlencode($value);
}

$data = new stdclass();
$data->business             = optional_param('business', '', PARAM_TEXT);
$data->receiver_email       = optional_param('receiver_email', '', PARAM_TEXT);
$data->receiver_id          = optional_param('receiver_id', '', PARAM_TEXT);
$data->item_name            = optional_param('item_name', '', PARAM_TEXT);
$data->memo                 = optional_param('memo', '', PARAM_TEXT);
$data->tax                  = optional_param('tax', '', PARAM_TEXT);
$data->option_name1         = optional_param('option_name1', '', PARAM_TEXT);
$data->option_selection1_x  = optional_param('option_selection1_x', '', PARAM_TEXT);
$data->option_name2         = optional_param('option_name2', '', PARAM_TEXT);
$data->option_selection2_x  = optional_param('option_selection2_x', '', PARAM_TEXT);
$data->payment_status       = optional_param('payment_status', '', PARAM_TEXT);
$data->pending_reason       = optional_param('pending_reason', '', PARAM_TEXT);
$data->reason_code          = optional_param('reason_code', '', PARAM_TEXT);
$data->txn_id               = optional_param('txn_id', '', PARAM_TEXT);
$data->parent_txn_id        = optional_param('parent_txn_id', '', PARAM_TEXT);
$data->payment_type         = optional_param('payment_type', '', PARAM_TEXT);
$data->payment_gross        = optional_param('mc_gross', '', PARAM_TEXT);
$data->payment_currency     = optional_param('mc_currency', '', PARAM_TEXT);
$custom = optional_param('custom', '', PARAM_TEXT);
$custom = explode('-', $custom);
$data->userid           = (int)$custom[0];
$data->contextid       = (int)$custom[1];
$data->timeupdated      = time();

if (! $user = $DB->get_record("user", array("id" => $data->userid))) {
    availability_paypal_message_error_to_admin("Not a valid user id", $data);
    die;
}

if (! $context = context::instance_by_id($data->contextid, IGNORE_MISSING)) {
    availability_paypal_message_error_to_admin("Not a valid context id", $data);
    die;
}

$instanceid = $context->instanceid;
if ($context instanceof context_module) {
    $availability = $DB->get_field('course_modules', 'availability', array('id' => $instanceid), MUST_EXIST);
    $availability = json_decode($availability);
    foreach ($availability->c as $condition) {
        if ($condition->type == 'paypal') {
            // TODO: handle more than one paypal for this context.
            $paypal = $condition;
            break;
        } else {
            availability_paypal_message_error_to_admin("Not a valid context id", $data);
        }
    }
} else {
    // TODO: handle sections.
    print_error('support to sections not yet implemented.');
}

// Open a connection back to PayPal to validate the data.
$paypaladdr = empty($CFG->usepaypalsandbox) ? 'www.paypal.com' : 'www.sandbox.paypal.com';
$c = new curl();
$options = array(
    'returntransfer' => true,
    'httpheader' => array('application/x-www-form-urlencoded', "Host: $paypaladdr"),
    'timeout' => 30,
    'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
);
$location = "https://{$paypaladdr}/cgi-bin/webscr";
$result = $c->post($location, $req, $options);

if (!$result) {  // Could not connect to PayPal - FAIL.
    echo "<p>Error: could not access paypal.com</p>";
    availability_paypal_message_error_to_admin("Could not access paypal.com to verify payment", $data);
    die;
}

// Connection is OK, so now we post the data to validate it.

// Now read the response and check if everything is OK.

if (strlen($result) > 0) {
    if (strcmp($result, "VERIFIED") == 0) {          // VALID PAYMENT!

        $DB->insert_record("availability_paypal_tnx", $data);

        // Check the payment_status and payment_reason.

        // If status is not completed, just tell admin, transaction will be saved later.
        if ($data->payment_status != "Completed" and $data->payment_status != "Pending") {
            availability_paypal_message_error_to_admin("Status not completed or pending. User payment status updated", $data);
        }

        // If currency is incorrectly set then someone maybe trying to cheat the system.
        if ($data->payment_currency != $paypal->currency) {
            availability_paypal_message_error_to_admin("Currency does not match course settings, received: ".$data->payment_currency, $data);
            die;
        }

        // If status is pending and reason is other than echeck,
        // then we are on hold until further notice.
        // Email user to let them know. Email admin.
        if ($data->payment_status == "Pending" and $data->pending_reason != "echeck") {

            $eventdata = new \core\message\message();
            $eventdata->component         = 'availability_paypal';
            $eventdata->name              = 'payment_pending';
            $eventdata->userfrom          = get_admin();
            $eventdata->userto            = $user;
            $eventdata->subject           = get_string("paypalpaymentpendingsubject", 'availability_paypal');
            $eventdata->fullmessage       = get_string('paypalpaymentpendingmessage', 'availability_paypal');
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }

        // If our status is not completed or not pending on an echeck clearance then ignore and die.
        // This check is redundant at present but may be useful if paypal extend the return codes in the future.
        if (! ( $data->payment_status == "Completed" or
               ($data->payment_status == "Pending" and $data->pending_reason == "echeck") ) ) {
            die;
        }

        // At this point we only proceed with a status of completed or pending with a reason of echeck.

        // Make sure this transaction doesn't exist already.
        if ($existing = $DB->get_record("availability_paypal_tnx", array("txn_id" => $data->txn_id))) {
            availability_paypal_message_error_to_admin("Transaction $data->txn_id is being repeated!", $data);
            die;
        }

        // Check that the email is the one we want it to be.
        if (core_text::strtolower($data->business) !== core_text::strtolower($paypal->businessemail)) {
            availability_paypal_message_error_to_admin("Business email is {$data->business} (not ".
                                            $paypal->businessemail.")", $data);
            die;
        }

        // Check that user exists.
        if (!$user = $DB->get_record('user', array('id' => $data->userid))) {
            availability_paypal_message_error_to_admin("User {$data->userid} doesn't exist", $data);
            die;
        }

        // Check that course exists.
        if (!$course = $DB->get_record('course', array('id' => $data->courseid))) {
            availability_paypal_message_error_to_admin("Course {$data->courseid} doesn't exist", $data);
            die;
        }

        $coursecontext = context_course::instance($course->id, IGNORE_MISSING);

        // Check that amount paid is the correct amount.
        if ( (float) $paypal->cost < 0 ) {
            $cost = (float) 0;
        } else {
            $cost = (float) $paypal->cost;
        }

        // Use the same rounding of floats as on the plugin form.
        $cost = format_float($cost, 2, false);

        if ($data->payment_gross < $cost) {
            availability_paypal_message_error_to_admin("Amount paid is not enough ({$data->payment_gross} < {$cost}))", $data);
            die;
        }

        // All clear!

        // Pass $view=true to filter hidden caps if the user cannot see them.
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                             '', '', '', '', false, true)) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = false;
        }

        /*
        $mailstudents = $paypal->mailstudents;
        $mailteachers = $paypal->mailteachers;
        $mailadmins   = $paypal->mailadmins;
        $shortname = format_string($course->shortname, true, array('context' => $context));

        if (!empty($mailstudents)) {
            $a = new stdClass();
            $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
            $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

            $eventdata = new \core\message\message();
            $eventdata->component         = 'availability_paypal';
            $eventdata->name              = 'payment_completed';
            $eventdata->userfrom          = empty($teacher) ? core_user::get_support_user() : $teacher;
            $eventdata->userto            = $user;
            $eventdata->subject           = get_string("paypalpaymentcompletedsubject", 'paypal');
            $eventdata->fullmessage       = get_string('paypalpaymentcompletedmessage', 'paypal');
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }

        if (!empty($mailteachers) && !empty($teacher)) {
            $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
            $a->user = fullname($user);

            $eventdata = new \core\message\message();
            $eventdata->component         = 'availability_paypal';
            $eventdata->name              = 'payment_completed';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $teacher;
            $eventdata->subject           = get_string("paypalpaymentcompletedsubject", 'paypal');
            $eventdata->fullmessage       = get_string('paypalpaymentcompletedmessage', 'paypal');
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }

        if (!empty($mailadmins)) {
            $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
            $a->user = fullname($user);
            $admins = get_admins();
            foreach ($admins as $admin) {
                $eventdata = new \core\message\message();
                $eventdata->component         = 'availability_paypal';
                $eventdata->name              = 'payment_completed';
                $eventdata->userfrom          = $user;
                $eventdata->userto            = $admin;
                $eventdata->subject           = get_string("paypalpaymentcompletedsubject", 'paypal');
                $eventdata->fullmessage       = get_string('paypalpaymentcompletedmessage', 'paypal');
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml   = '';
                $eventdata->smallmessage      = '';
                message_send($eventdata);
            }
        }
        */

    } else if (strcmp ($result, "INVALID") == 0) { // ERROR.
        $DB->insert_record("availability_paypal_tnx", $data, false);
        availability_paypal_message_error_to_admin("Received an invalid payment notification!! (Fake payment?)", $data);
    }
}

function availability_paypal_message_error_to_admin($subject, $data) {
    $admin = get_admin();
    $site = get_site();

    $message = "$site->fullname:  Transaction failed:{$subject}";

    foreach ($data as $key => $value) {
        $message .= "{$key} => {$value};";
    }

    $eventdata = new stdClass();
    $eventdata->component         = 'availability_paypal';
    $eventdata->name              = 'payment_error';
    $eventdata->userfrom          = $admin;
    $eventdata->userto            = $admin;
    $eventdata->subject           = "PayPal ERROR: ".$subject;
    $eventdata->fullmessage       = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';
    $eventdata->smallmessage      = '';
    message_send($eventdata);
}

/**
 * Silent exception handler.
 *
 * @param Exception $ex
 * @return void - does not return. Terminates execution!
 */
function availability_paypal_ipn_exception_handler($ex) {
    $info = get_exception_info($ex);

    $logerrmsg = "availability_paypal IPN exception handler: ".$info->message;
    if (debugging('', DEBUG_NORMAL)) {
        $logerrmsg .= ' Debug: '.$info->debuginfo."\n".format_backtrace($info->backtrace, true);
    }
    mtrace($logerrmsg);
    exit(0);
}
