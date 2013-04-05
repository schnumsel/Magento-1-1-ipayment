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

class Phoenix_Ipayment_Block_Jsinclude extends Mage_Core_Block_Template
{
    protected $_accountData = array(
                                'accountId' => 99999,
                                'trxUserId' => 99999,
                                'trxUserPassword' => 0
                              );

    public function getAccountData()
    {
        $data = $this->_accountData;

        if ($this->isMethodEnabled('cc') &&
            ($data['accountId'] = $this->_getConfig('payment/ipayment_cc/account_id')) &&
            ($data['trxUserId'] = $this->_getConfig('payment/ipayment_cc/trxuser_id')) &&
            ($data['trxUserPassword'] = $this->_getConfig('payment/ipayment_cc/trxuser_password'))) {
            return $data;
        }

        if ($this->isMethodEnabled('elv') &&
            ($data['accountId'] = $this->_getConfig('payment/ipayment_elv/account_id')) &&
            ($data['trxUserId'] = $this->_getConfig('payment/ipayment_elv/trxuser_id')) &&
            ($data['trxUserPassword'] = $this->_getConfig('payment/ipayment_elv/trxuser_password'))) {
            return $data;
        }

        return $this->_accountData;;
    }

    public function isMethodEnabled($code)
    {
        return Mage::getStoreConfigFlag('payment/ipayment_'.$code.'/active');
    }

    protected function _getConfig($path)
    {
        $value = Mage::getStoreConfig($path);
        if (empty($value)) {
            return false;
        }
        return $value;
    }
}
