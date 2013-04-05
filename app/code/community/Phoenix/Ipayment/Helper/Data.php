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
 * @copyright  Copyright (c) 2008 Phoenix Medien GmbH & Co. KG (http://www.phoenix-medien.de)
 */

/**
 * data helper
 */
class Phoenix_Ipayment_Helper_Data extends Mage_Payment_Helper_Data
{
    public function getCcTypes()
    {
        $_types = Mage::getConfig()->getNode('default/phoenix/ipayment/cctypes')->asArray();

        uasort($_types, array('Mage_Payment_Model_Config', 'compareCcTypes'));

        $types = array();
        foreach ($_types as $data) {
            $types[$data['code']] = $data['name'];
        }
        return $types;
    }

    public function getPendingPaymentStatus()
    {
        if (version_compare(Mage::getVersion(), '1.4.0', '<')) {
            return Mage_Sales_Model_Order::STATE_HOLDED;
        }
        return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
    }

    public function get3dSecureRedirectMessage()
    {
        return $this->__('Customer was redirected for 3D-Secure payment.');
    }
}
