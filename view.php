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
 * Prints a particular instance of paypal
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    availability_paypal
 * @copyright  2015 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');

$contextid = required_param('contextid', PARAM_INT);

$context = context::instance_by_id($contextid);
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
            print_error('no paypal condition for this context.');
        }
    }
} else {
    // TODO: handle sections.
    print_error('support to sections not yet implemented.');
}
$coursecontext = $context->get_course_context();
$course = $DB->get_record('course', array('id' => $coursecontext->instanceid));

require_login($course);

if ($paymenttnx = $DB->get_record('availability_paypal_tnx', array('userid' => $USER->id, 'contextid' => $contextid))) {

    if ($paymenttnx->payment_status == 'Completed') {
        redirect($context->get_url(), get_string('paymentcompleted', 'availability_paypal'));
    }
}

$PAGE->set_url('/availability/condition/paypal/view.php', array('contextid' => $contextid));
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->fullname);

$PAGE->navbar->add($paypal->itemname);

echo $OUTPUT->header();

echo $OUTPUT->heading($paypal->itemname);

if ($paymenttnx && ($paymenttnx->payment_status == 'Pending')) {
    echo get_string('paymentpending', 'availability_paypal');
} else {

    // Calculate localised and "." cost, make sure we send PayPal the same value,
    // please note PayPal expects amount with 2 decimal places and "." separator.
    $localisedcost = format_float($paypal->cost, 2, true);
    $cost = format_float($paypal->cost, 2, false);

    if (isguestuser()) { // Force login only for guest user, not real users with guest role.
        if (empty($CFG->loginhttps)) {
            $wwwroot = $CFG->wwwroot;
        } else {
            // This actually is not so secure ;-), 'cause we're in unencrypted connection...
            $wwwroot = str_replace("http://", "https://", $CFG->wwwroot);
        }
        echo '<div class="mdl-align"><p>'.get_string('paymentrequired', 'availability_paypal').'</p>';
        echo '<div class="mdl-align"><p>'.get_string('paymentwaitremider', 'availability_paypal').'</p>';
        echo '<p><b>'.get_string('cost').": $instance->currency $localisedcost".'</b></p>';
        echo '<p><a href="'.$wwwroot.'/login/">'.get_string('loginsite').'</a></p>';
        echo '</div>';
    } else {
        // Sanitise some fields before building the PayPal form.
        $userfullname    = fullname($USER);
        $userfirstname   = $USER->firstname;
        $userlastname    = $USER->lastname;
        $useraddress     = $USER->address;
        $usercity        = $USER->city;
?>
        <p><?php print_string("paymentrequired", 'availability_paypal') ?></p>
        <p><b><?php echo get_string("cost").": {$paypal->currency} {$localisedcost}"; ?></b></p>
        <p><img alt="<?php print_string('paypalaccepted', 'availability_paypal') ?>"
        title="<?php print_string('paypalaccepted', 'availability_paypal') ?>"
        src="https://www.paypal.com/en_US/i/logo/PayPal_mark_60x38.gif" /></p>
        <p><?php print_string("paymentinstant", 'availability_paypal') ?></p>
        <?php
        if (empty($CFG->usepaypalsandbox)) {
            $paypalurl = 'https://www.paypal.com/cgi-bin/webscr';
        } else {
            $paypalurl = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        }
        ?>
        <form action="<?php echo $paypalurl ?>" method="post">

            <input type="hidden" name="cmd" value="_xclick" />
            <input type="hidden" name="charset" value="utf-8" />
            <input type="hidden" name="business" value="<?php p($paypal->businessemail)?>" />
            <input type="hidden" name="item_name" value="<?php p($paypal->itemname) ?>" />
            <input type="hidden" name="item_number" value="<?php p($paypal->itemnumber) ?>" />
            <input type="hidden" name="quantity" value="1" />
            <input type="hidden" name="on0" value="<?php print_string("user") ?>" />
            <input type="hidden" name="os0" value="<?php p($userfullname) ?>" />
            <input type="hidden" name="custom" value="<?php echo "{$USER->id}-{$contextid}" ?>" />

            <input type="hidden" name="currency_code" value="<?php p($paypal->currency) ?>" />
            <input type="hidden" name="amount" value="<?php p($cost) ?>" />

            <input type="hidden" name="for_auction" value="false" />
            <input type="hidden" name="no_note" value="1" />
            <input type="hidden" name="no_shipping" value="1" />
            <input type="hidden" name="notify_url" value="<?php echo "{$CFG->wwwroot}/availability/condition/paypal/ipn.php" ?>" />
            <input type="hidden" name="return" value="<?php echo "{$CFG->wwwroot}/availability/condition/paypal/view.php?contextid={$contextid}" ?>" />
            <input type="hidden" name="cancel_return" value="<?php echo "{$CFG->wwwroot}/availability/condition/paypal/view.php?contextid={$contextid}" ?>" />
            <input type="hidden" name="rm" value="2" />
            <input type="hidden" name="cbt" value="<?php print_string("continue", 'availability_paypal') ?>" />

            <input type="hidden" name="first_name" value="<?php p($userfirstname) ?>" />
            <input type="hidden" name="last_name" value="<?php p($userlastname) ?>" />
            <input type="hidden" name="address" value="<?php p($useraddress) ?>" />
            <input type="hidden" name="city" value="<?php p($usercity) ?>" />
            <input type="hidden" name="email" value="<?php p($USER->email) ?>" />
            <input type="hidden" name="country" value="<?php p($USER->country) ?>" />

            <input type="submit" value="<?php print_string("sendpaymentbutton", "availability_paypal") ?>" />
        </form>
<?php
    }
}
// Finish the page.
echo $OUTPUT->footer();
