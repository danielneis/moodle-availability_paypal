/**
 * JavaScript for form editing date conditions.
 *
 * @module moodle-availability_paypal-form
 */
M.availability_paypal = M.availability_paypal || {};

/**
 * @class M.availability_paypal.form
 * @extends M.core_availability.plugin
 */
M.availability_paypal.form = Y.Object(M.core_availability.plugin);

M.availability_paypal.form.getNode = function(json) {
    // See https://www.paypal.com/cgi-bin/webscr?cmd=p/sell/mc/mc_intro-outside,
    // 3-character ISO-4217:
    // https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_currency_codes
    var codes = ['AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY',
                 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RUB', 'SEK', 'SGD', 'THB', 'TRY', 'TWD', 'USD'];
    var currencies_options = '';
    var selected_string = '';
    codes.forEach(function(c) {
        if (json.currency && json.currency == c) {
            selected_string = ' selected="selected" ';
        } else {
            selected_string = '';
        }
        currencies_options += '<option value="'+c+'" '+selected_string+' >' +
                                M.util.get_string(c, 'availability_paypal') +
                              '</option>';
    });

    var html = '<div><label>' +
                M.util.get_string('businessemail', 'availability_paypal') +
               '<input name="businessemail" type="email" /></label></div>' +
              
                '<div><label>' +
                 M.util.get_string('currency', 'availability_paypal') +
                '<select name="currency" type="text" />'+currencies_options+'</select></label></div>' +

                '<div><label>' +
                 M.util.get_string('cost', 'availability_paypal') +
                '<input name="cost" type="float" /></label></div>' +

                '<div><label>' +
                 M.util.get_string('itemname', 'availability_paypal') +
                '<input name="itemname" type="text" /></label></div>' +

                '<div><label>' +
                 M.util.get_string('itemnumber', 'availability_paypal') +
                '<input name="itemnumber" type="text" /></label></div>' ;
    var node = Y.Node.create('<span>' + html + '</span>');

    // Set initial values based on the value from the JSON data in Moodle
    // database. This will have values undefined if creating a new one.
    if (json.businessemail) {
        node.one('input[name=businessemail]').set('value', json.businessemail);
    }
    if (json.cost) {
        node.one('input[name=cost]').set('value', json.cost);
    }
    if (json.itemname) {
        node.one('input[name=itemname]').set('value', json.itemname);
    }
    if (json.itemnumber) {
        node.one('input[name=itemnumber]').set('value', json.itemnumber);
    }

    // Add event handlers (first time only). You can do this any way you
    // like, but this pattern is used by the existing code.
    if (!M.availability_paypal.form.addedEvents) {
        M.availability_paypal.form.addedEvents = true;
        var root = Y.one('#fitem_id_availabilityconditionsjson');
        root.delegate('change', function() {
            // For the grade item, just update the form fields.
            M.core_availability.form.update();
        }, '.availability_paypal select[name=currency]');
        root.delegate('valuechange', function() {
                // The key point is this update call. This call will update
                // the JSON data in the hidden field in the form, so that it
                // includes the new value of the checkbox.
                M.core_availability.form.update();
        }, '.availability_paypal input');
    }

    return node;
};

M.availability_paypal.form.fillValue = function(value, node) {
    // This function gets passed the node (from above) and a value
    // object. Within that object, it must set up the correct values
    // to use within the JSON data in the form. Should be compatible
    // with the structure used in the __construct and save functions
    // within condition.php.
    value.businessemail = node.one('input[name=businessemail]').get('value');

    value.currency = node.one('select[name=currency]').get('value');

    value.cost = node.one('input[name=cost]').get('value');

    value.itemname = node.one('input[name=itemname]').get('value');

    value.itemnumber = node.one('input[name=itemnumber]').get('value');
};

M.availability_paypal.form.fillErrors = function(errors, node) {
    var value = {};
    this.fillValue(value, node);

    if (value.businessemail === '') {
        errors.push('availability_paypal:error_businessemail');
    }
    var cost = parseFloat(value.cost);
    if ((cost !== undefined && typeof(cost) === 'string') || cost <= 0 ) {
        errors.push('availability_paypal:error_cost');
    }
    if (value.itemname === '') {
        errors.push('availability_paypal:error_itemname');
    }
    if (value.itemnumber === '') {
        errors.push('availability_paypal:error_itemnumber');
    }
};
