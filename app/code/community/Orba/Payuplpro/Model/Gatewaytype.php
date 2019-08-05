<?php

class Orba_Payuplpro_Model_Gatewaytype extends Mage_Core_Model_Abstract {
    
    const PAYUPLPRO_GATEWAYTYPE_AFTER_CHECKOUT = 'after_checkout';
    const PAYUPLPRO_GATEWAYTYPE_DURING_CHECKOUT = 'during_checkout';
    
    public function toOptionArray() {
        return array(
            array(
                'value' => Orba_Payuplpro_Model_Gatewaytype::PAYUPLPRO_GATEWAYTYPE_AFTER_CHECKOUT,
                'label' => Mage::helper('payuplpro')->__('After Checkout')
            ),
            array(
                'value' => Orba_Payuplpro_Model_Gatewaytype::PAYUPLPRO_GATEWAYTYPE_DURING_CHECKOUT,
                'label' => Mage::helper('payuplpro')->__('During Checkout')
            )
        );
    }
    
}