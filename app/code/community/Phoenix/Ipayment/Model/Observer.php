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

class Phoenix_Ipayment_Model_Observer
{
    public function emptyCcSession($observer)
    {
        Mage::getSingleton('core/session')->unsetData('ipayment_cc_info');
        return;
    }

    public function sales_order_payment_place_end($observer)
    {
        $payment = $observer->getPayment();
        if ($payment->getMethod() == 'ipayment_cc'
                && version_compare(Mage::getVersion(), '1.4.0', '<')
                && $payment->getMethodInstance()->getFormHtml()) {
            $order = $payment->getOrder();
            $order->setState(
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage::helper('phoenix_ipayment')->getPendingPaymentStatus(),
                Mage::helper('phoenix_ipayment')->get3dSecureRedirectMessage()
            );
        }
    }
}