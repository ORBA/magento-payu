<?php

class Orba_Payuplpro_Model_Encoding extends Mage_Core_Model_Abstract {
    
    const PAYUPLPRO_ENCODING_UTF = 'UTF';
    
    public function toOptionArray() {
        return array(
            array(
                'value' => Orba_Payuplpro_Model_Encoding::PAYUPLPRO_ENCODING_UTF,
                'label' => 'UTF-8'
            )
        );
    }
    
}