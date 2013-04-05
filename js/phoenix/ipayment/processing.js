/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @copyright  Copyright (c) 2010 Phoenix Medien GmbH & Co. KG (http://www.phoenix-medien.de)
 */

/**
 * Add wrapper to the original payment.save method.
 * The payment object will be available after the page has been loaded.
 * @see: opcheckout.js payment prototype
 */

function enableElements(elements) {
	for (var i=0; i<elements.length; i++) {
    	elements[i].disabled = false;
	}
}
function ipaymentDisplayCcForm() {
	$('payment_form_ipayment_cc').contentWindow.$('ipayment_cc_iframe_cont').show();
	$('payment_form_ipayment_cc').contentWindow.$('ipaymentDisplayCcFormLink').hide();
    $('ipayment_cc_additional_data').value = '';
}

function ipaymentObserver(ev) {
    if (payment.currentMethod == 'ipayment_cc') {
        var ipayment = new ipaymentMultishipping();
        if ($('ipayment_cc_request_status').value != 'SUCCESS' ) {
            ipayment.save();
            Event.stop(ev);
        }
    }
    if (payment.currentMethod == 'ipayment_elv') {
        var ipayment = new ipaymentMultishipping();
        if ($('ipayment_elv_request_status').value != 'SUCCESS' ) {
            ipayment.save();
            Event.stop(ev);
        }
    }
}

var multishipping = false;
Event.observe(window, 'load', function() {
    var form = $('multishipping-billing-form');
    if (form) {
        Event.observe(form, 'submit', ipaymentObserver);
        return;
    }
    
    if ($$('.multi-address-checkout-box #review-button').length>0 || $$('.multiple-checkout #review-button').length>0) {
    	var reviewContainerClass = $$('.multi-address-checkout-box #review-button').length>0
    		? 'multi-address-checkout-box'
    		: 'multiple-checkout';
		$$('.' + reviewContainerClass + ' #review-button').each(function(el){
			Event.observe(el, 'click', function(ev) {
				Event.stop(ev);
				new Ajax.Request(
		        	unsetSessInfoUrlGlobal,
		            {
		                method:'post',
		                onSuccess: function(){
		        			el.up('form').submit();
		        		}
		        	}
		        );
		    });
		});
		return;
    }
    
	payment.save = payment.save.wrap(function(origSaveMethod){
		if (this.currentMethod && this.currentMethod.substr(0,9) == 'ipayment_') {
			if (checkout.loadWaiting!=false) return;
			
			if (this.currentMethod == 'ipayment_cc') {
				var elements = $('fieldset_ipayment_cc').select('input[type="hidden"]');
				enableElements(elements);
				if ($('ipayment_cc_additional_data').value.length!=0) {
					origSaveMethod();
					return;
				}
				var valid = $('payment_form_ipayment_cc').contentWindow.validate();
			}
			else {
				var validator = new Validation(this.form);
				var valid = validator.validate();
			}
			
			if (this.validate() && valid) {
				checkout.setLoadWaiting('payment');
				if (this.currentMethod.substr(9) == 'cc') {
                    var application = new ipaymentApplication();
                    var data = application.buildCcData();
                    application.sendIpaymentRequest(data, cc_trxuser_id, 'processOnepagecheckoutResponse');
                } else if (this.currentMethod.substr(9) == '3ds') {
                    var application = new ipaymentApplication();
                    var data = application.buildCcData();
                    application.sendIpaymentRequest(data, _3ds_trxuser_id, 'processOnepagecheckoutResponse');
				} else {
                    var application = new ipaymentApplication();
                    var data = application.buildElvData();
                    application.sendIpaymentRequest(data, elv_trxuser_id, 'processOnepagecheckoutResponse');
                }
			}
		} else {
			origSaveMethod();
		}
	});

    payment.switchMethod = payment.switchMethod.wrap(function(parentMethod,method){
        parentMethod(method);
        if (method){
            this.currentMethod = method;
        }
    });

});

var ipaymentMultishipping = Class.create();
ipaymentMultishipping.prototype = {
     initialize: function(){
         this.form = $('multishipping-billing-form');
     },
     validate: function() {
        var methods = document.getElementsByName('payment[method]');
        if (methods.length==0) {
            alert(Translator.translate('Your order can not be completed at this time as there is no payment methods available for it.'));
            return false;
        }
        for (var i=0; i<methods.length; i++) {
            if (methods[i].checked) {
                return true;
            }
        }
        alert(Translator.translate('Please specify payment method.'));
        return false;
    },
    save: function() {
        if (payment.currentMethod == 'ipayment_cc') {
			var elements = $('fieldset_ipayment_cc').select('input[type="hidden"]');
			enableElements(elements);
			if ($('ipayment_cc_additional_data').value.length!=0) {
				this.form.submit();
				return true;
			}
			var valid = $('payment_form_ipayment_cc').contentWindow.validate();
			multishipping = true;
		}
		else {
			var validator = new Validation(this.form);
			var valid = validator.validate();
		}
        
        if (this.validate() && valid) {
            if (payment.currentMethod.substr(9) == 'cc') {
                var application = new ipaymentApplication();
                var data = application.buildCcData();
                application.sendIpaymentRequest(data, cc_trxuser_id, 'processMultishippingCcResponse');
                Element.show('ipayment-cc-please-wait');
            }
            if (payment.currentMethod.substr(9) == 'elv') {
                var application = new ipaymentApplication();
                var data = application.buildElvData();
                application.sendIpaymentRequest(data, elv_trxuser_id, 'processMultishippingElvResponse');
                Element.show('ipayment-elv-please-wait');
            }
        }
    }
}



var ipaymentApplication = Class.create();
ipaymentApplication.prototype = {
     initialize: function(){
     },
     buildCcData: function() {
        var data = {
            trx_paymenttyp:		'cc',
            addr_name:			$('payment_form_ipayment_cc').contentWindow.$(payment.currentMethod+'_cc_owner').value,
            cc_number:			$('payment_form_ipayment_cc').contentWindow.$(payment.currentMethod+'_cc_number').value,
            cc_expdate_month:	$('payment_form_ipayment_cc').contentWindow.$(payment.currentMethod+'_expiration').value,
            cc_expdate_year:	$('payment_form_ipayment_cc').contentWindow.$(payment.currentMethod+'_expiration_yr').value,
            cc_checkcode:		$('payment_form_ipayment_cc').contentWindow.$(payment.currentMethod+'_cc_cid').value,
            error_lang:			document.getElementsByTagName('html')[0].getAttribute('lang')
        }
        return data;
     },
     buildElvData: function() {
        var data = {
            trx_paymenttyp:		'elv',
            addr_name:			$(payment.currentMethod+'_owner').value,
            bank_code:			$(payment.currentMethod+'_bank_code').value,
            bank_name:			$(payment.currentMethod+'_bank_name').value,
            bank_country:		$(payment.currentMethod+'_bank_country').value,
            bank_accountnumber:	$(payment.currentMethod+'_account_number').value,
            error_lang:			document.getElementsByTagName('html')[0].getAttribute('lang')
        }
        return data;
     },
     sendIpaymentRequest: function (data, trxuser_id, callback ){
        var request = new IpaymentRequest(
            data, {
                return_type : 'object',
                callback_function_name : callback
            }, trxuser_id);
        request.checkAndStore();
     }
}

function processOnepagecheckoutResponse(response) {
    processResponse (response);

    if (response.get('ret_status') == 'SUCCESS') {
        if (response.get('paydata_bank_name'))
            document.getElementById('ipayment_elv_bank_name').value = response.get('paydata_bank_name');

        new Ajax.Request(
        	saveSessInfoUrlGlobal,
            {
                method:'post',
                parameters: Form.serialize(payment.form)
            }
        );
        
        var request = new Ajax.Request(
            payment.saveUrl,
            {
                method:'post',
                onComplete: payment.onComplete,
                onSuccess: payment.onSave,
                onFailure: checkout.ajaxFailure.bind(checkout),
                parameters: Form.serialize(payment.form)
            }
        );
    }
}

function processMultishippingCcResponse (response) {
    $('ipayment_cc_request_status').value = response.get('ret_status');
    Element.hide('ipayment-cc-please-wait');
    processResponse (response);
    if (response.get('ret_status') == 'SUCCESS') {
        // Disable form fields before submit.
        var form  = $('multishipping-billing-form');
        var f = document.getElementById('multishipping-billing-form');
        
        new Ajax.Request(
        	saveSessInfoUrlGlobal,
            {
                method:'post',
                parameters: Form.serialize($('multishipping-billing-form')),
                asynchronous: false
            }
        );
        
        f.submit();
    }
}

function processMultishippingElvResponse (response) {
    $('ipayment_elv_request_status').value = response.get('ret_status');
    Element.hide('ipayment-elv-please-wait');
    processResponse (response);
    if (response.get('ret_status') == 'SUCCESS') {
        var f = document.getElementById('multishipping-billing-form');
        f.submit();
    }
}


function processResponse (response) {
    if (response.get('ret_status') == 'SUCCESS') {
        prepareSubmitedFields(response);
    } else if (response.get('ret_status') == 'ERROR') {
        processError(response);
    } else {
        alert('Payment status "'+response.get('ret_status')+'" not implemented yet.');
    }
}


function processError(response) {
    alert(response.get('ret_errormsg'));
    if (!multishipping) checkout.setLoadWaiting(false);
    switch (response.get('ret_errorcode')) {
        case '5000':
        case '5009':
            // name wrong
            break;
        case '5002':
        case '5007':
            // cc number wrong
            break;
        case '5003':
        case '5004':
            // cc date wrong
            break;
        case '5005':
        case '5006':
            // ccv wrong
            break;
        }
}

function prepareSubmitedFields(response) {
	var elements = $('fieldset_ipayment_cc').select('input[type="hidden"]');
	$('payment_form_ipayment_cc').contentWindow.updateHiddenElements(elements);
	$$('input[name="payment[additional_data]"]').each(function(el){el.value = response.get('storage_id')});;
}