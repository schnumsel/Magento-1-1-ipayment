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

class Phoenix_Ipayment_Model_Source_CcType
{
    public function toOptionArray()
    {
        $options =  array();

        foreach (Mage::helper('phoenix_ipayment')->getCcTypes() as $code => $name) {
        	$options[] = array(
        	   'value' => $code,
        	   'label' => $name
        	);
        }

        return $options;
    }
}