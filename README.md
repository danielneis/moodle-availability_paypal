PayPal Availability Condition for Moodle
----------------------------------------

With this plugins, you can put a price in any course content and ask for a PayPal payment to allow access.

The person in charge to configure the enrolment method on the course will be able to configure the enrolment cost's value and currency.

The user will be able to pay in some other currency at PayPal website. The conversion rates will be calculated and applied by PayPal.

It works only with "course modules and resources". The support to "course sections" or "topics" is not yet implemented.

Install
-------

* Put these files at moodle/availability/condition/paypal/
 * You may use composer
 * or git clone
 * or download the latest version from https://github.com/danielneis/moodle-availability_paypal/archive/master.zip
* Log in your Moodle as Admin and go to "Notifications" page
* Follow the instructions to install the plugin
* You must activate the IPN at your PayPal account
* You must also use HTTPS on your Moodle site

Usage
-----

This works like the [PayPal enrol plugin](https://docs.moodle.org/en/Paypal_enrolment), but instead of restricting the full course, you can restrict individual activities, resources or sections (and you can combine it with other availability conditions, for example, to exclude some group from paying using an "or" restriction set).

For each restriction you add, you can set a business email address, cost, currency, item name and item number.

In case of problems with the payment, all users with the capability "Receive payment notifications" (availability/paypal:receivenotifications) are notified via email and Moodle messaging. If there is no dedicated user with that capability, all site administrators are notified by default.


Funding
-------

The development of this plugin was funded by TRREE - TRAINING AND RESOURCES IN RESEARCH ETHICS EVALUATION - http://www.trree.org/

Dev Info
--------

Please, report issues at: https://github.com/danielneis/moodle-availability_paypal/issues

Feel free to send pull requests at: https://github.com/danielneis/moodle-availability_paypal/pulls

[![Travis-CI Build Status](https://travis-ci.org/danielneis/moodle-availability_paypal.svg?branch=master)](https://travis-ci.org/danielneis/moodle-availability_paypal)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/danielneis/moodle-availability_paypal/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/danielneis/moodle-availability_paypal/?branch=master)
