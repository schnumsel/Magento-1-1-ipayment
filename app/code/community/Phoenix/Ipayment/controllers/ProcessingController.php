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
 * @copyright  Copyright (c) 2010 Phoenix Medien GmbH & Co. KG (http://www.phoenix-medien.de)
 */

class Phoenix_Ipayment_ProcessingController extends Mage_Core_Controller_Front_Action
{
    protected $_order = null;

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
	 * when customer select ipayment payment method
	 */
	public function redirectAction()
	{
	    try {
	        $session = $this->_getCheckout();

            $this->_debug('redirectAction()');

    		$order = Mage::getModel('sales/order');
    		$order->loadByIncrementId($session->getLastRealOrderId());
            if (!$order->getId()) {
                Mage::throwException(Mage::helper('phoenix_ipayment')->__('An error occured during the payment process: Order not found.'));
            }
            if ($order->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    $this->_getPendingPaymentStatus(),
                    Mage::helper('phoenix_ipayment')->get3dSecureRedirectMessage()
                )->save();
            }

            if ($session->getQuoteId() && $session->getLastSuccessQuoteId()) {
                $session->setIpaymentQuoteId($session->getQuoteId());
                $session->setIpaymentLastSuccessQuoteId($session->getLastSuccessQuoteId());
                $session->setIpaymentRealOrderId($session->getLastRealOrderId());
                $session->getQuote()->setIsActive(false)->save();
                $session->clear();
            }

            $this->loadLayout();
            $this->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch(Exception $e) {
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
	}

	public function returnAction()
	{
        try {
            // load quote and order
            $this->_loadCheckoutObjects();

            // get essentiall parameters
            $paRes = $this->getRequest()->getParam('PaRes');
            $md = $this->getRequest()->getParam('MD');
            // do not proceed on empty parameters
            if (empty($paRes) || empty($md)) {
                $this->norouteAction();
                return;
            }

            $payment = $this->_order->getPayment()->getMethodInstance();
            $response = $payment->paymentAuthenticationReturn($md, $paRes);

            $this->_debug('returnAction():$response='.print_r($response, 1));

            if ($response->status != 'SUCCESS') {
                Mage::throwException(Mage::helper('phoenix_ipayment')->__('An error during the credit card processing occured: %s', $response->errorDetails->retErrorMsg));
            }

            // log 3D-Secure information
            $this->_order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                $payment->getConfigData('order_status', $this->_order->getStoreId()),
                $this->_getSuccessStatusMessage($response->successDetails->trxPayauthStatus)
            );

            // set transaction ID
            $this->_order->getPayment()
                ->setLastTransId($response->successDetails->retTrxNumber)
                ->setCcTransId($response->successDetails->retTrxNumber);

            foreach ($this->_order->getInvoiceCollection() as $orderInvoice) {
                if ($orderInvoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_OPEN &&
                	$orderInvoice->getGrandTotal() == $this->_order->getGrandTotal()) {
                    $orderInvoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)->save();
                    $this->_order->setTotalPaid($orderInvoice->getGrandTotal());
                    break;
                }
            }            
            
            // send new order email to customer
            $this->_order->sendNewOrderEmail()->setEmailSent(true)->save();

            // payment is okay. show success page.
            $this->_getCheckout()->setLastSuccessQuoteId($this->_order->getQuoteId());
            $this->_redirect('checkout/onepage/success');
            return;
        } catch(Mage_Core_Exception $e) {
            $this->_debug($e->getMessage());

            // payment authentication was not successful. cancel order.
            if ($this->_order &&
                $this->_order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT &&
                $this->_order->canCancel()) {

                $this->_order->cancel()
                    ->addStatusToHistory($this->_order->getState(), $e->getMessage())
                    ->save();

                // set quote to active
                if ($quoteId = $this->_getCheckout()->getQuoteId()) {
                    $quote = Mage::getModel('sales/quote')->load($quoteId);
                    if ($quote->getId()) {
                        $quote->setIsActive(true)->save();
                    }
                }

                $msg = Mage::helper('phoenix_ipayment')->__('The order has been canceled.');
            } else {
                $msg = Mage::helper('phoenix_ipayment')->__('Has payment authentication already been processed for this order?');
            }
            $this->_getCheckout()->addError($msg);
        } catch(Exception $e) {
            Mage::logException($e);
        }

        $this->_redirect('checkout/cart');
	}

    /**
     * Load quote and order objects from session
     */
    protected function _loadCheckoutObjects()
    {
            // load quote
        if ($quoteId = $this->_getCheckout()->getIpaymentQuoteId(true)) {
            $this->_getCheckout()->setQuoteId($quoteId);
        } else {
            Mage::throwException(Mage::helper('phoenix_ipayment')->__('Checkout session is empty.'));
        }

            // load order
        $this->_order = Mage::getModel('sales/order');
        $this->_order->loadByIncrementId($this->_getCheckout()->getIpaymentRealOrderId(true));
        if (!$this->_order->getId()) {
            Mage::throwException(Mage::helper('phoenix_ipayment')->__('An error occured during the payment process: Order not found.'));
        }
    }

    protected function _getSuccessStatusMessage($statusCode)
    {
        switch($statusCode) {
            case 'I':
                $message = Mage::helper('phoenix_ipayment')->__('Successful 3D-Secure authentication. Status: I. Issuer is authenticated');
                break;
            case 'U':
                $message = Mage::helper('phoenix_ipayment')->__('3D-Secure authentication. Status: U. 3D-Secure not available. Transaction is performed');
                break;
            case 'M':
                $message = Mage::helper('phoenix_ipayment')->__('3D-Secure authentication. Status: M. Card doesn\'t support 3D-Secure. Transaction is performed');
                break;
            default:
                $message = Mage::helper('phoenix_ipayment')->__('3D-Secure authentication. Status: %s. Transaction is performed', $statusCode);
        }
        return $message;
    }

    protected function _getPendingPaymentStatus()
    {
        return Mage::helper('phoenix_ipayment')->getPendingPaymentStatus();
    }

    public function ccformAction()
    {
       $this->loadLayout();
       $this->renderLayout();
    }

    public function saveSessInfoAction()
    {
        $payment = $this->getRequest()->getPost();

        if (!empty($payment) && isset($payment['payment']) && !empty($payment['payment'])) {
            $payment = $payment['payment'];
            if ($payment['method']=='ipayment_cc' && !empty($payment['cc_owner']) && !empty($payment['cc_type'])
                && !empty($payment['cc_number']) && !empty($payment['cc_exp_month']) && !empty($payment['cc_exp_year'])
                && !empty($payment['additional_data'])) {
                Mage::getSingleton('core/session')->setIpaymentCcInfo($payment);
            }
        }

        return;
    }

    public function unsetSessInfoAction()
    {
        if ($this->getRequest()->isPost()) {
            Mage::getSingleton('core/session')->unsetData('ipayment_cc_info');
        }
        return;
    }

    /**
     * Log debug data to file
     *
     * @param mixed $debugData
     */
    protected function _debug($debugData)
    {
        if (Mage::getStoreConfigFlag('payment/ipayment_cc/debug')) {
            Mage::log($debugData, null, 'payment_ipayment_cc.log', true);
        }
    }
}