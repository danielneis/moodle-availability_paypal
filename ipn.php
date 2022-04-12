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

define('NO_MOODLE_COOKIES', 1);
define('NO_DEBUG_DISPLAY', 1);

// This file do not require login because paypal service will use to confirm transactions.
// @codingStandardsIgnoreLine
require(__DIR__ . '/../../../config.php');

require_once($CFG->libdir . '/filelib.php');

// PayPal does not like when we return error messages here,
// the custom handler just logs exceptions and stops.
set_exception_handler('availability_paypal_ipn_exception_handler');

// Keep out casual intruders.
if (empty($_POST) or !empty($_GET)) {
    die("Sorry, you can not use the script that way.");
}

// Read all the data from PayPal and get it ready for later;
// we expect only valid UTF-8 encoding, it is the responsibility
// of user to set it up properly in PayPal business account,
// it is documented in docs wiki.
$req = 'cmd=_notify-validate';

foreach ($_POST as $key => $value) {
    $req .= "&$key=" . urlencode($value);
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

$custom = optional_param('custom', '', PARAM_ALPHANUMEXT);
$custom = explode('-', $custom);

if (count($custom) != 4 || $custom[0] !== 'availability_paypal') {
    // This is not IPN for this plugin.
    debugging('availability_paypal IPN: custom value does not match expected format');
    die();
}

$data->userid = (int) ($custom[1] ?? -1);
$data->contextid = (int) ($custom[2] ?? -1);
$data->sectionid = (int) ($custom[3] ?? -1);

$data->timeupdated = time();

debugging('availability_paypal IPN incoming notification: ' . json_encode($data), DEBUG_DEVELOPER);

if (!$user = $DB->get_record("user", array("id" => $data->userid))) {
    $PAGE->set_context(context_system::instance());
    availability_paypal_message_error("Not a valid user id", $data);
    die;
}

if (!$context = context::instance_by_id($data->contextid, IGNORE_MISSING)) {
    $PAGE->set_context(context_system::instance());
    availability_paypal_message_error("Not a valid context id", $data);
    die;
}

$PAGE->set_context($context);

if ($context instanceof context_module) {
    $availability = $DB->get_field('course_modules', 'availability', ['id' => $context->instanceid], MUST_EXIST);
} else {
    $availability = $DB->get_field('course_sections', 'availability', ['id' => $data->sectionid], MUST_EXIST);
}

$availability = json_decode($availability);

$paypal = null;

if ($availability) {
    // There can be multiple conditions specified. Find the first of the type "paypal".
    // TODO: Support more than one paypal condition specified.
    foreach ($availability->c as $condition) {
        if ($condition->type == 'paypal') {
            $paypal = $condition;
            break;
        }
    }
}

if (empty($paypal)) {
    availability_paypal_message_error("PayPal condition not found while processing incoming IPN", $data);
    die();
}

// Make a temporary record of the incoming IPN. It will be deleted once the payment is verified. If the verification
// fails, it will be kept and will allow the admin to debug and/or verify it manually.
$DB->insert_record("availability_paypal_tnx", array_merge((array) $data, [
    'payment_status' => 'ToBeVerified',
]), false);

// Open a connection back to PayPal to validate the data.
$paypaladdr = empty($CFG->usepaypalsandbox) ? 'ipnpb.paypal.com' : 'ipnpb.sandbox.paypal.com';
$c = new curl();
$options = array(
    'CURLOPT_RETURNTRANSFER' => 1,
    'CURLOPT_HTTPHEADER' => [
        'Host: ' . $paypaladdr,
        'Content-Type: application/x-www-form-urlencoded',
        'Connection: Close',
    ],
    'CURLOPT_TIMEOUT' => 30,
    'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
    'CURLOPT_FORBID_REUSE' => 1,
);
$location = "https://{$paypaladdr}/cgi-bin/webscr";

debugging('availability_paypal IPN verification request: ' . json_encode($req), DEBUG_DEVELOPER);

// Number of attempts to verify the payment.
$attempts = 5;

while ($attempts) {
    $attempts--;
    $result = trim($c->post($location, $req, $options));
    $info = $c->get_info();

    if ($c->get_errno()) {
        availability_paypal_message_error("Could not access paypal.com to verify payment", $data);
        die;
    }

    if ($info['http_code'] == 200) {
        break;

    } else {
        debugging('availability_paypal IPN verification unexpected response code: ' . $info['http_code'], DEBUG_DEVELOPER);

        if ($attempts) {
            sleep(1);
        }
    }
}

debugging('availability_paypal IPN verification response: ' . $result, DEBUG_DEVELOPER);

if (strlen($result) > 0) {
    if (strcmp($result, "VERIFIED") == 0) {

        // Make sure the transaction with the same payment status and same pending reason doesn't exist already.
        if ($DB->record_exists("availability_paypal_tnx", [
            'txn_id' => $data->txn_id,
            'payment_status' => $data->payment_status,
            'pending_reason' => $data->pending_reason,
        ])) {
            availability_paypal_message_error("Transaction $data->txn_id is being repeated!", $data);
            die;
        }

        // In case of unexpected status, warn admins.
        if ($data->payment_status !== "Completed" && $data->payment_status !== "Pending") {
            $str = "Status neither completed nor pending: " . $data->payment_status;
            availability_paypal_message_error($str, $data);
            die;
        }

        // If currency is incorrectly set then someone maybe trying to cheat the system.
        if ($data->payment_currency !== $paypal->currency) {
            $str = "Currency does not match course settings, received: " . $data->payment_currency;
            availability_paypal_message_error($str, $data);
            die;
        }

        // If cost is incorrectly set then someone maybe trying to cheat the system.
        if ($data->payment_gross != $paypal->cost) {
            $str = "Payment gross does not match course settings, received: " . $data->payment_gross;
            availability_paypal_message_error($str, $data);
            die;
        }

        // If status is pending and reason is other than echeck, then we are on hold until further notice.
        // Email the user to let them know.
        if ($data->payment_status === "Pending" && $data->pending_reason !== "echeck") {

            $eventdata = new \core\message\message();
            $eventdata->component         = 'availability_paypal';
            $eventdata->name              = 'payment_pending';
            $eventdata->userfrom          = core_user::get_noreply_user();
            $eventdata->userto            = $user;
            $eventdata->subject           = get_string('paypalpaymentpendingsubject', 'availability_paypal');
            $eventdata->fullmessage       = get_string('paypalpaymentpendingmessage', 'availability_paypal');
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = text_to_html($eventdata->fullmessage);
            $eventdata->smallmessage      = $eventdata->subject;
            message_send($eventdata);
        }

        // At this point we only proceed with a status of completed or pending.
        $DB->insert_record("availability_paypal_tnx", $data, false);

    } else {
        $DB->insert_record("availability_paypal_tnx", array_merge((array) $data, [
            'payment_status' => 'Unverified',
        ]), false);

        $data->verification_result = s($result);
        availability_paypal_message_error("Payment verification failed", $data);
    }

    // Remove the temporary transaction record.
    $DB->delete_records("availability_paypal_tnx", [
        'payment_status' => 'ToBeVerified',
        'txn_id' => $data->txn_id,
        'userid' => $data->userid,
        'contextid' => $data->contextid,
        'sectionid' => $data->sectionid,
    ]);
}

/**
 * Sends message to admin about error
 *
 * @param string $subject
 * @param stdClass $data
 */
function availability_paypal_message_error($subject, $data) {

    $userfrom = core_user::get_noreply_user();
    $recipients = get_users_by_capability(context_system::instance(), 'availability/paypal:receivenotifications');

    if (empty($recipients)) {
        // Make sure that someone is notified.
        $recipients = get_admins();
    }

    $site = get_site();

    $text = "$site->fullname: PayPal transaction problem: {$subject}\n\n";
    $text .= "Transaction data:\n";

    if ($data) {
        foreach ($data as $key => $value) {
            $text .= "* {$key} => {$value}\n";
        }
    }

    foreach ($recipients as $recipient) {
        $message = new \core\message\message();
        $message->component = 'availability_paypal';
        $message->name = 'payment_error';
        $message->userfrom = $userfrom;
        $message->userto = $recipient;
        $message->subject = "PayPal ERROR: " . $subject;
        $message->fullmessage = $text;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html($text);
        $message->smallmessage = $subject;

        // Don't make one error to stop all other notifications.
        try {
            message_send($message);

        } catch (Throwable $t) {
            debugging('availability_paypal IPN: exception while sending message: ' . $t->getMessage());
        }
    }
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
    debugging($logerrmsg);
    exit(0);
}
