<?php

class Orba_Payuplpro_Model_Sales_Order_Payment extends Mage_Sales_Model_Order_Payment {
    
    protected function getConfig() {
        return Mage::getSingleton('payuplpro/config');  
    }
    
    public function registerPayuplproCaptureNotification($amount) {
        ///// Copy of Mage_Sales_Model_Order_Payment::registerCaptureNotification()
        
        $this->_generateTransactionId(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE,
            $this->getAuthorizationTransaction()
        );

        $order   = $this->getOrder();
        $amount  = (float)$amount;
        $invoice = $this->_getInvoiceForTransactionId($this->getTransactionId());

        // register new capture
        if (!$invoice) {
            if ($this->_isCaptureFinal($amount)) {
                $invoice = $order->prepareInvoice()->register();
                $order->addRelatedObject($invoice);
                $this->setCreatedInvoice($invoice);
            } else {
                $this->setIsFraudDetected(true);
                $this->_updateTotals(array('base_amount_paid_online' => $amount));
            }
        }
        $status = true;
        if ($this->getIsTransactionPending()) {
            $message = Mage::helper('sales')->__('Capturing amount of %s is pending approval on gateway.', $this->_formatPrice($amount));
            $state = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
            if ($this->getIsFraudDetected()) {
                $message = Mage::helper('sales')->__('Order is suspended as its capture amount %s is suspected to be fraudulent.', $this->_formatPrice($amount));
                $status = Mage_Sales_Model_Order::STATUS_FRAUD;
            }
        } else {
            $message = Mage::helper('sales')->__('Registered notification about captured amount of %s.', $this->_formatPrice($amount));
            $state = Mage_Sales_Model_Order::STATE_PROCESSING;
            if ($this->getIsFraudDetected()) {
                $state = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
                $message = Mage::helper('sales')->__('Order is suspended as its capture amount %s is suspected to be fraudulent.', $this->_formatPrice($amount));
                $status = Mage_Sales_Model_Order::STATUS_FRAUD;
            }
            // register capture for an existing invoice
            if ($invoice && Mage_Sales_Model_Order_Invoice::STATE_OPEN == $invoice->getState()) {
                $invoice->pay();
                $this->_updateTotals(array('base_amount_paid_online' => $amount));
                $order->addRelatedObject($invoice);
            }
        }
        $transaction = $this->_addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, $invoice, true);
        $message = $this->_prependMessage($message);
        $message = $this->_appendTransactionToMessage($transaction, $message);
        
        ///// Payu.pl Pro status
        if ($state === Mage_Sales_Model_Order::STATE_PROCESSING) {
            $store_id = $order->getStoreId();
            $status = $this->getConfig()->getDefaultStatus($state, $store_id);
        }
        $order->setState($state, $status, $message);
        return $this;
    }
    
}