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


class Phoenix_Ipayment_Model_Cc extends Phoenix_Ipayment_Model_Abstract
{
    const XML_PATH_ENABLE_TEST_MODE = 'payment/ipayment_cc/enable_test_mode';

	protected $_code = 'ipayment_cc';
    protected $_formBlockType = 'ipayment/form_cc';
    protected $_infoBlockType = 'ipayment/info_cc';
    protected $_paymentMethod = 'cc';
    protected $_canSaveCc = true;
    protected $_canUseForMultishipping  = true;
    protected $_canCapturePartial       = true;
    protected $_canRefundInvoicePartial = true;

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
		if (!($data instanceof Varien_Object)) {
			$data = new Varien_Object($data);
		}
		$info = $this->getInfoInstance();
		$info->setCcType($data->getCcType())
			->setCcOwner($data->getCcOwner())
			->setCcLast4(substr($data->getCcNumber(), -4))
			->setCcNumber(substr($data->getCcNumber(), -4))
			->setCcExpMonth($data->getCcExpMonth())
			->setCcExpYear($data->getCcExpYear())
            ->setPoNumber($data->getAdditionalData());
		return $this;
    }

    /**
     * Prepare info instance for save
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function prepareSave()
    {
        $info = $this->getInfoInstance();
        if ($this->_canSaveCc) {
            $info->setCcNumberEnc($info->encrypt($info->getCcNumber()));
        }
        $info->setCcNumber(null)
            ->setCcCid(null);
        return $this;
    }

    /**
     * Method redirects customer on 3D-Secure payments
     *
     * @return unknown_type
     */
	public function getOrderPlaceRedirectUrl()
	{
		if ($this->getFormHtml()) {
			return Mage::getUrl('ipayment/processing/redirect');
        } else {
			return false;
        }
	}

    /**
     * Performs paymentAuthenticationReturn request to Ipayment server
     * @param <type> $MD
     * @param <type> $PaRes
     */
    public function paymentAuthenticationReturn($md, $paRes)
    {
        $options = array('MD' => $md, 'PaRes' =>$paRes);
        $response = $this->_processRequest('paymentAuthenticationReturn', $options, $this);
        return $response;
    }

    /**
     * Processes the response for different response statuses
     *
     * @param string $request
     * @param mixed $result
     * @param Varien_Object $payment
     *
     */

    protected function _beforeProcessResponse($request, $result, Varien_Object $payment)
    {
        parent::_beforeProcessResponse($request, $result, $payment);

        switch ($result->status){
            case 'SUCCESS':
                switch ($request){
                    case 'preAuthorize':
                    case 'authorize':
                        $payment->setCcTransId($result->successDetails->retTrxNumber);
                        break;
                }
                break;
            case 'REDIRECT':
                switch ($request){
                    case 'preAuthorize':
                        $payment->getOrder()->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                        if (Mage::getSingleton('checkout/type_multishipping_state')
                                ->getActiveStep() ==
                                    Mage_Checkout_Model_Type_Multishipping_State::STEP_OVERVIEW) {
                            Mage::throwException(Mage::helper('phoenix_ipayment')->__('3D-Secure Credit Cards are not allowed with Multiple Addresses Checkout'));
                        }
                        break;
                    case 'authorize':
                        $payment->getOrder()->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                        if (Mage::getSingleton('checkout/type_multishipping_state')
                                ->getActiveStep() ==
                                    Mage_Checkout_Model_Type_Multishipping_State::STEP_OVERVIEW) {
                            Mage::throwException(Mage::helper('phoenix_ipayment')->__('3D-Secure Credit Cards are not allowed with Multiple Addresses Checkout'));
                        }
                        //for Magento 1.4
                        $payment->setIsTransactionPending(true);
                        //for Magento 1.3
                        $payment->setForcedState(Mage_Sales_Model_Order_Invoice::STATE_OPEN);
                        break;
                }
                break;
        }
    }
    /**
     * Called before the request is processed
     *
     * @param string $request
     * @param mixed $options
     * @param Varien_Object $payment
     */

    protected function _beforeProcessRequest($request, &$options, Varien_Object $payment){
        switch ($request) {
            case 'capture':
                $options['origTrxNumber'] = ($payment->getCcTransId() ? $payment->getCcTransId() : $payment->getLastTransId());
                break;
            case 'refund':
                $options['origTrxNumber'] = $payment->getRefundTransactionId();
                break;
        }
    }

    protected function _getOptionData(Varien_Object $payment)
	{
        if (!is_array($this->_optionData)) {
            parent::_getOptionData($payment);
            if (Mage::getStoreConfig(self::XML_PATH_ENABLE_TEST_MODE)) {
                $this->_optionData['checkFraudattack'] = false;
                $this->_optionData['checkDoubleTrx'] = false;
            }
        }

		return $this->_optionData;
    }
}
