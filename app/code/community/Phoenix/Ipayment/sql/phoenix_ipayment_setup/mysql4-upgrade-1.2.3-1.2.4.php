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

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$installer->run("
UPDATE `{$installer->getTable('core_config_data')}`
    SET `value` = 0
    WHERE `path`='payment/ipayment_3ds/active';
");

// due to a change in the order table structure we need to test the existence first
if ($installer->tableExists('sales_flat_quote_payment')) {
    $installer->run("UPDATE `{$installer->getTable('sales_flat_quote_payment')}` SET `method` = 'ipayment_cc' WHERE `method` = 'ipayment_3ds';");
}
if ($installer->tableExists('sales_order_entity_xvarchar')) {
    $installer->run("UPDATE `{$installer->getTable('sales_order_entity_varchar')}` SET `value` = 'ipayment_cc'  WHERE `value` = 'ipayment_3ds';");
}

$installer->endSetup();
