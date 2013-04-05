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


class Phoenix_Ipayment_Model_Elv extends Phoenix_Ipayment_Model_Abstract
{
	protected $_code = 'ipayment_elv';
    protected $_formBlockType = 'ipayment/form_elv';
    protected $_infoBlockType = 'ipayment/info_elv';
    protected $_paymentMethod = 'elv';
    
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
		$info->setCcType($data->getBankName())
			->setCcOwner($data->getOwner())
			->setCcLast4(substr($data->getAccountNumber(), -4))
			->setCcNumber($data->getAccountNumber())
			->setCcNumberEnc($data->getBankCode())
            ->setPoNumber($data->getAdditionalData());
		return $this;
    }
}
