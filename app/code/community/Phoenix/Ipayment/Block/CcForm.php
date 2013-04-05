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
 * @category   Mage
 * @package    Phoenix_Ipayment
 * @copyright  Copyright (c) 2010 Phoenix Medien GmbH & Co. KG (http://www.phoenix-medien.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Phoenix_Ipayment_Block_CcForm extends Mage_Core_Block_Template
{
    protected $_method = null;
    protected $_form = null;
    
    public function getForm()
    {
        if (!$this->_form) {
            $this->_form = Mage::getBlockSingleton('ipayment/form_cc');
            $this->_form->setMethod($this->getMethod());
        }
        return $this->_form;
    }

    protected function _getConfig()
    {
        return Mage::getSingleton('ipayment/config');
    }

    protected function _getOrder()
    {
        if ($this->getOrder()) {
            return $this->getOrder();
        } elseif ($orderIncrementId = $this->_getCheckout()->getLastRealOrderId()) {
            return Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        } else {
            return null;
        }
    }
    
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function jsTranslate($message)
    {
        $data = array(
            $message => $this->__($message)
        );
        return Zend_Json::encode($data);
    }

    public function getBillingName()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $addr = $quote->getBillingAddress();
        return $addr->getFirstname() . ' ' . $addr->getLastname();
    }
    
    public function getMethod()
    {
        if (!$this->_method) {
            $this->_method = Mage::getModel('phoenix_ipayment/cc');
        }
        return $this->_method;
    }
    
    public function getMethodCode()
    {
        return $this->getMethod()->getCode();
    }
    
}