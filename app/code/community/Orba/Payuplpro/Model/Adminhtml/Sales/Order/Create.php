<?php
class Orba_Payuplpro_Model_Adminhtml_Sales_Order_Create extends Mage_Adminhtml_Model_Sales_Order_Create {
    
    public function initFromOrder(Mage_Sales_Model_Order $order) {
        if ($order->getPayment()->getMethod() == 'payuplpro') {
            $order->getPayment()
                ->unsAdditionalInformation('payuplpro_customer_sid')
                ->unsAdditionalInformation('payuplpro_paytype')
                ->setAdditionalInformation('payuplpro_online', false)
                ->setAdditionalInformation('payuplpro_is_completed', false)
                ->setAdditionalInformation('payuplpro_is_completed_outside', false)
                ->setAdditionalInformation('payuplpro_try_number', 0)
                ->setAdditionalInformation('payuplpro_state', -1)
                ->setAdditionalInformation('payuplpro_hash', Mage::helper('payuplpro')->getHash());
        }
        return parent::initFromOrder($order);
    }
    
}