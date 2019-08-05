<?php
class Orba_Payuplpro_Model_Observer extends Mage_Core_Model_Abstract {
    
    public function getConfig() {
        return Mage::getModel('payuplpro/config');  
    }
    
    public function cancelPayment($observer) {
        $event = $observer->getEvent();
		$payment = $event->getPayment();
        $payment_model = Mage::getModel('payuplpro/payment');
        if ($payment->getMethod() == 'payuplpro' && $payment_model->isCancellationEnabled($payment)) {
            $payment_model->cancelTransaction($payment);
        }
    }
    
}