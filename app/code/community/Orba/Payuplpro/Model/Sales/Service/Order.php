<?php

class Orba_Payuplpro_Model_Sales_Service_Order extends Mage_Sales_Model_Service_Order {
    
    protected function _initCreditmemoData($creditmemo, $data){
        if (isset($data['shipping_amount'])) {
            $creditmemo->setBaseShippingAmount((float)$this->changeComasToDots($data['shipping_amount']));
        }
        if (isset($data['adjustment_positive'])) {
            $creditmemo->setAdjustmentPositive($this->changeComasToDots($data['adjustment_positive']));
        }
        if (isset($data['adjustment_negative'])) {
            $creditmemo->setAdjustmentNegative($this->changeComasToDots($data['adjustment_negative']));
        }
    }
    
    protected function changeComasToDots($val) {
        return str_replace(',', '.', $val);
    }

}