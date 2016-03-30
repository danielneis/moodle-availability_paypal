PayPal Availability Condition for Moodle
----------------------------------------

With this plugins, you can put a price in any course content and ask for a PayPal payment to allow access.

Install
-------

* Put these files at moodle/availability/condition/paypal/
 * You may use composer
 * or git clone
 * or download the latest version from https://github.com/danielneis/moodle-availability_paypal/archive/master.zip
* Log in your Moodle as Admin and go to "Notifications" page
* Follow the instructions to install the plugin

Usage
-----

This works like the [PayPal enrol plugin](https://docs.moodle.org/en/Paypal_enrolment), but instead of restricting the full course, you can restrict individual activities, resources or sections (and you can combine it with other availability conditions, for example, to exclude some group from paying using an "or" restriction set).

For each restriction you add, you can set a business email address, cost, currency, item name and item number.

Funding
-------

The development of this plugin was funded by TRREE - TRAINING AND RESOURCES IN RESEARCH ETHICS EVALUATION - http://www.trree.org/

Dev Info
--------

Please, report issues at: https://github.com/danielneis/moodle-availability_paypal/issues

Feel free to send pull requests at: https://github.com/danielneis/moodle-availability_paypal/pulls

[![Travis-CI Build Status](https://travis-ci.org/danielneis/moodle-availability_paypal.svg?branch=master)](https://travis-ci.org/danielneis/moodle-availability_paypal)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/danielneis/moodle-availability_paypal/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/danielneis/moodle-availability_paypal/?branch=master)
