<?php
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
 * @category   Phoenix
 * @package    Phoenix_Ipayment
 * @copyright  Copyright (c) 2009 Phoenix Medien GmbH & Co. KG (http://www.phoenix-medien.de)
 */


abstract class Phoenix_Ipayment_Model_Abstract extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Availability options
     */
    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;

    /**
     * Module identifiers
     */
    protected $_code 					= 'ipayment_abstract';
    protected $_paymentMethod			= 'abstract';

    /**
     * Internal objects and arrays for SOAP communication
     */
    protected $_service					= NULL;
    protected $_accountData				= NULL;
	protected $_paymentData				= NULL;
	protected $_optionData				= NULL;


    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Authorize (Preauthroization)
     *
     * @param   Varien_Object $orderPayment
     * @return  Mage_Payment_Model_Abstract
     */
	public function authorize(Varien_Object $payment, $amount)
	{
		parent::authorize($payment, $amount);

		$order = $payment->getOrder();
        $options = array('transactionData' => array(
                'trxAmount'         =>  round($amount * 100),
                'trxCurrency'       =>  $order->getBaseCurrencyCode(),
                'invoiceText'       =>  $this->_getInvoiceText($order),
                'trxUserComment'    =>  $order->getRealOrderId() . '-' . $order->getQuoteId(),
                'shopperId'         =>  $order->getRealOrderId(),
            ));

		$this->_processRequest('preAuthorize', $options, $payment);

		return $this;
    }

    /**
     * Capture payment (Authorize + Capture)
     *
     * @param   Varien_Object $orderPayment
     * @return  Mage_Payment_Model_Abstract
     */
	public function capture(Varien_Object $payment, $amount)
	{
		parent::capture($payment, $amount);

		$order = $payment->getOrder();
        $options = array('transactionData' => array(
                'trxAmount'         =>  round($amount * 100),
                'trxCurrency'       =>  $order->getBaseCurrencyCode(),
                'invoiceText'       =>  $this->_getInvoiceText($order),
                'trxUserComment'    =>  $order->getRealOrderId() . '-' . $order->getQuoteId(),
                'shopperId'         =>  $order->getRealOrderId(),
            ));

	        // check if transaction has been preauthorized (lastTransId exists)
            // and no capture already performed
            //   -> use "capture" instead
        if ($payment->getLastTransId()) {
        	$options['origTrxNumber'] = $payment->getLastTransId();
        	$this->_processRequest('capture', $options, $payment);
        } else {
			$this->_processRequest('authorize', $options, $payment);
        }

		return $this;
	}

    /**
     * Cancel payment
     *
     * @param   Varien_Object $invoicePayment
     * @return  Mage_Payment_Model_Abstract
     */
	public function cancel(Varien_Object $payment, $amount = null)
	{
		$amount = (is_null($amount)) ? $payment->getOrder()->getBaseTotalDue() : $amount;

		$options = array(
			'origTrxNumber'		=>	$payment->getLastTransId(),
			'transactionData'	=>	array(
				'trxAmount'         =>  round($amount * 100),
				'trxCurrency'       =>  $payment->getOrder()->getBaseCurrencyCode(),
			));

			// try to reverse the payment (for preauthorized payments)
		$res = $this->_processRequest('reverse', $options, $payment);

			// if the reverse call failed try to refund the payment (for authorized payments)
		if ($res === false) {
			try {
				$this->_processRequest('refund', $options, $payment);
				$payment->getOrder()->addStatusToHistory(
					Mage_Sales_Model_Order::STATE_CANCELED,
					Mage::helper('phoenix_ipayment')->__('Payment has been refunded.')
				);
			}
			catch (Mage_Core_Exception $e) {
					// if the payment can't be refunded add an error message and continue
				Mage::getSingleton('adminhtml/session')->addError(
					Mage::helper('phoenix_ipayment')->__('Ipayment error: %s', $e->getMessage())
				);
				$payment->getOrder()->addStatusToHistory(
					Mage_Sales_Model_Order::STATE_CANCELED,
					Mage::helper('phoenix_ipayment')->__('Failed to refund payment.')
				);
			}
		} else {
			$payment->getOrder()->addStatusToHistory(
				Mage_Sales_Model_Order::STATE_CANCELED,
				Mage::helper('phoenix_ipayment')->__('Payment has been reversed.')
			);
		}

		return $this;
	}

    /**
     * Refund money
     *
     * @param   Varien_Object $invoicePayment
     * @return  Mage_Payment_Model_Abstract
     */
    //public function refund(Varien_Object $payment, $amount)
    public function refund(Varien_Object $payment, $amount)
    {
    	parent::refund($payment, $amount);
		$this->cancel($payment, $amount);

        return $this;
    }

    /**
     * Void payment
     *
     * @param   Varien_Object $invoicePayment
     * @return  Mage_Payment_Model_Abstract
     */
    public function void(Varien_Object $payment)
    {
    	parent::void();
		$this->cancel($payment);

        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @param   Mage_Payment_Model_Info $info
     * @return  Mage_Payment_Model_Abstract
     */
    public function validate()
    {
        parent::validate();
        $info = $this->getInfoInstance();
        $errorMsg = false;
        $storageId = $info->getPoNumber();
		if (empty($storageId) || !is_numeric($storageId))
			$errorMsg = Mage::helper('phoenix_ipayment')->__('Storage ID is invalid.');

        if ($errorMsg)
            Mage::throwException($errorMsg);

        return $this;
    }

	/**
	 * Create and return SOAP client
	 *
	 * @return object	SOAP client
	 */
	protected function getService()
	{
		if (!is_object($this->_service)) {
			try {
					// start SOAP client
				$service = new SoapClient($this->getConfigData('wsdl'), array('trace' => true, 'exceptions' => true));
					// connection successfull established
				$this->_service = $service;
			}
			catch (Exception $e) {
				$this->_debug('Ipayment connection failed: '.$e->getMessage());
				Mage::throwException(
					Mage::helper('phoenix_ipayment')->__('Can not connect payment service. Please try again later.')
				);
			}
		}
		return $this->_service;
	}

	protected function getAccountData()
	{
		if (!is_array($this->_accountData)) {
			$this->_accountData = array(
				'accountId'				=>	$this->getConfigData('account_id'),
				'trxuserId'				=>	$this->getConfigData('trxuser_id'),
				'trxpassword'			=>	$this->getConfigData('trxuser_password'),
				'adminactionpassword'	=>	$this->getConfigData('adminaction_password'),
			);
		}

		return $this->_accountData;
	}

	protected function getPaymentData(Varien_Object $payment)
	{
		if (!is_array($this->_paymentData)) {
		    $billing = $payment->getOrder()->getBillingAddress();

	        $this->_paymentData =	array(
				'storageData'   =>  array(
					'fromDatastorageId'     =>  $payment->getPoNumber()
				),
				'addressData'	=>	array(
					'addrName'		=>	$payment->getCcOwner(),
                    'addrStreet'	=>  $this->_normalizeStreet($billing->getStreet()),
                    'addrCity'      =>  $billing->getCity(),
                    'addrZip'       =>  $billing->getPostcode(),
					'addrCountry'	=>	$billing->getCountry(),
					'addrEmail'		=>	$payment->getOrder()->getCustomerEmail(),
				),
			);
		}
        return $this->_paymentData;
	}

	protected function _getOptionData(Varien_Object $payment)
	{
		if (!is_array($this->_optionData)) {

            $order    = $payment->getOrder();
            $clientIp = '127.0.0.1';

            if ($order->getXForwardedFor()) {
                $clientIp = $order->getXForwardedFor();
            } else {
                $clientIp = ($order->getRemoteIp() != '::1') ? $order->getRemoteIp() : $clientIp;
            }

			$this->_optionData	=	array(
				'fromIp'		=>	$clientIp,
				'clientData'	=>	array(
										'clientName'			=>	'Magento '.Mage::getVersion(),
										'clientVersion'			=>	'Phoenix Ipayment Modul v' .
											Mage::getConfig()->getNode('modules/Phoenix_Ipayment/version'),
									),
				'browserData'	=>	array(
										'browserUserAgent'		=>	$_SERVER['HTTP_USER_AGENT'],
										'browserAcceptHeaders'	=>	$_SERVER['HTTP_ACCEPT'],
									),
				'otherOptions'	=>	array(
										'option'				=>	array(
																		'key'	=>	'ppceeef',
																		'value'	=>	'c3d8249c014e02fb151e57b'
																	)
									)
			);
		}

		return $this->_optionData;
	}

	/**
	 * Processes web service request and returns response
	 *
	 * @param	string	request function that should be called
	 * @param	array	request options (depends on request. see wsdl.)
	 * @return	mixed	result return by iPaymentService::_processResponse()
	 */
	protected function _processRequest($request, $options, Varien_Object $payment)
	{
        $this->_debug('_processRequest():$request='.$request.'; $options='.print_r($options, 1));
        $this->_beforeProcessRequest($request, $options, $payment);
		try {
			switch ($request) {
				case 'authorize':
					$result = $this->getService()->authorize(
						$this->getAccountData(),
						$this->getPaymentData($payment),
						$options['transactionData'],
						$this->_getOptionData($payment)
					);
					break;
				case 'preAuthorize':
					$result = $this->getService()->preAuthorize(
						$this->getAccountData(),
						$this->getPaymentData($payment),
						$options['transactionData'],
						$this->_getOptionData($payment)
					);
					break;
				case 'capture':
					$result = $this->getService()->capture(
						$this->getAccountData(),
						$options['origTrxNumber'],
						$options['transactionData'],
						$this->_getOptionData($payment)
					);
					break;

				case 'reverse':
					$result = $this->getService()->reverse(
						$this->getAccountData(),
						$options['origTrxNumber'],
						$options['transactionData'],
						$this->_getOptionData($payment)
					);
					break;
				case 'refund':
					$result = $this->getService()->refund(
						$this->getAccountData(),
						$options['origTrxNumber'],
						$options['transactionData'],
						$this->_getOptionData($payment)
					);
					break;
				case 'paymentAuthenticationReturn':
					$result = $this->getService()->paymentAuthenticationReturn(
						array('MD' => $options['MD'], 'PaRes' => $options['PaRes'])
					);
                    break;
				default:
					$this->_debug('Ipayment unhandled web service request "'.$request.'".');
			}
		}
		catch (SoapFault $fault) {
			$this->_debug("Ipayment SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})");
			Mage::throwException(
				Mage::helper('phoenix_ipayment')->__('Can not connect payment service. Please try again later.')
			);
			return false;
		}catch (Exception $e){
            $this->_debug("Ipayment SOAP Fault: (faultcode: {$e->getCode()}, faultstring: {$e->getMessage()})");
            Mage::throwException(
            	Mage::helper('phoenix_ipayment')->__('Can not prepare a request for the payment service. Please check your data.')
            );
        }

			// process response and return result
		return $this->_processResponse($request, $result, $payment);
	}

	/**
	 * Processes the web service response
	 *
	 * @param	string	request function that has been called
	 * @param	mixed	result of the request
	 * @return	boolean	true on "success", false if the request result shoudl be regarded as "failed"
	 */
	protected function _processResponse($request, $result, Varien_Object $payment)
    {
        $this->_debug('_processResponse():$request='.$request.';$result.='.print_r($result, 1));
        $this->_beforeProcessResponse($request, $result, $payment);
			// clear ipayment redirect information
		$this->setFormHtml();

		switch ($request) {
			case 'authorize':
			case 'capture':
			case 'preAuthorize':
			case 'refund':
				if ($result->status == 'SUCCESS') {
					$payment->setLastTransId($result->successDetails->retTrxNumber);
					$payment->setCcApproval($result->successDetails->retAuthCode);
					return true;
				}
				if ($result->status == 'ERROR') {
					Mage::throwException($result->errorDetails->retErrorMsg.' ('.$result->errorDetails->retErrorcode.')');
				}
				if ($result->status == 'REDIRECT') {
					$this->setFormHtml($result->redirectDetails->redirectData);
					return true;
				}
				break;
            case 'paymentAuthenticationReturn':
                return $result;
                break;
			case 'reverse':
				if ($result->status == 'SUCCESS') {
					$payment->setLastTransId($result->successDetails->retTrxNumber);
					return true;
				}
				if ($result->status == 'ERROR')
					return false;
				break;
			default:
				$this->_debug('Ipayment unhandled web service response "'.$request.'".');
		}
		return false;
	}

    /**
     * Success response processor
     *
     * @param string $request
     * @param mixed $result
     * @param Varien_Object $payment
     *
     */
    protected function _beforeProcessResponse($request, $result, Varien_Object $payment)
    {
        switch ($result->status){
            case 'SUCCESS':
                switch ($request){
                    case 'preAuthorize':
                        $payment->getOrder()->setCustomerNote(Mage::helper('phoenix_ipayment')->__('Payment has been preauthorized.'));
                        break;
                    case 'authorize':
                        $payment->getOrder()->setCustomerNote(Mage::helper('phoenix_ipayment')->__('Payment has been authorized and captured.'));
                        break;
                    case 'capture':
                        $payment->getOrder()->setCustomerNote(Mage::helper('phoenix_ipayment')->__('Payment has been captured.'));
                        break;
                }
                break;
            case 'REDIRECT':
                switch ($request){
                    case 'preAuthorize':
                        $payment->getOrder()->setCustomerNote(Mage::helper('phoenix_ipayment')->__('Payment preauthorization started.'));
                        break;
                    case 'authorize':
                        $payment->getOrder()->setCustomerNote(Mage::helper('phoenix_ipayment')->__('Payment authorization started.'));
                        break;
                }
                break;
        }
    }

    protected function _beforeProcessRequest($request, &$options, Varien_Object $payment)
    {
    }

    /**
     * Save form html in checkout session
     *
     * @param   string
     * @return  $this
     */
    public function setFormHtml($formHtml = '')
    {
        if (!empty($formHtml)) {
            $formHtml = str_replace('%REDIRECT_RETURN_SCRIPT%', Mage::getUrl('ipayment/processing/return'), $formHtml);
        }
        $this->_getCheckout()->setIpaymentRedirectFormHtml($formHtml);
        return $this;
    }

    /**
     * Return form html for redirect
     *
     * @see Phoenix_Ipayment_Model_Abstract::_processResponse
     * @param   bool    clear value in session
     * @return  string  form html
     */
    public function getFormHtml($clear = false)
    {
        return $this->_getCheckout()->getIpaymentRedirectFormHtml($clear);
    }

    /**
     * Compile invoiceText string. Supported placeholders are '{{store_name}}',
     * '{{order_no}}'.
     *
     * @param Mage_Sales_Model_Order $order
     */
    protected function _getInvoiceText(Mage_Sales_Model_Order $order)
    {
        $invoiceText = str_replace(
            array('{{store_name}}', '{{order_no}}'),
            array(Mage::app()->getStore($order->getStoreId())->getName(), $order->getRealOrderId()),
            $this->getConfigData('invoice_text', $order->getStoreId())
        );
        return $invoiceText;
    }

    /**
     * Log debug data to file
     *
     * Prior Magento 1.4.1 this method doesn't exists. So it is mainly to provide
     * BC.
     *
     * @param mixed $debugData
     */
    protected function _debug($debugData)
    {
        if (method_exists($this, 'getDebugFlag')) {
            return parent::_debug($debugData);
        }

        if ($this->getConfigData('debug')) {
            Mage::log($debugData, null, 'payment_' . $this->getCode() . '.log', true);
        }
    }

    protected function _normalizeStreet($street)
    {
        if (!is_array($street)) {
            return $street;
        }
        return implode(' ', $street);
    }
}