<?php

class Orba_Payuplpro_Model_Gateway extends Mage_Core_Model_Abstract {
    
    const PAYUPLPRO_GATEWAY_PRODUCTION = 'https://www.platnosci.pl/paygw';
    const PAYUPLPRO_GATEWAY_SANDBOX = 'https://sandbox.payu.pl/paygw';
    
    public function toOptionArray() {
        return array(
            array(
                'value' => Orba_Payuplpro_Model_Gateway::PAYUPLPRO_GATEWAY_PRODUCTION,
                'label' => Mage::helper('payuplpro')->__('Production')
            ),
            array(
                'value' => Orba_Payuplpro_Model_Gateway::PAYUPLPRO_GATEWAY_SANDBOX,
                'label' => Mage::helper('payuplpro')->__('Sandbox')
            )
        );
    }
    
}