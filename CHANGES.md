== Release 16 ==

* Added a new 'PayPal payments' overview report allowing to check all recorded
  transactions and their status.
* Fix IPN listener so that it does not attempt to process notifications not
  originating in this plugin.
* Avoid falsely sent "Transaction is being repeated" emails.
* Fix payment verification so that unverified payment does not unlock the restricted
  area.
* Requires Moodle 3.11 or higher version.

== Release 15 ==

* Notifications on pending payments and payment errors fixed and improved.
* There is a new capability "Receive payment notifications"
  (availability/paypal:receivenotifications) that controls who should be notified
  about payment errors. If unused, the plugin will notify all site admins by default.
* Overall review and improvements of the IPN handler script.
