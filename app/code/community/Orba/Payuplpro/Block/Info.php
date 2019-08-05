<?php

/**
 * Class Orba_Payuplpro_Block_Info
 *
 * @property Mage_Sales_Model_Order              order
 * @property Mage_Sales_Model_Order_Payment      payment
 * @property array                               result
 */
class Orba_Payuplpro_Block_Info extends Mage_Payment_Block_Info {

    protected $result;
    protected $order;
    protected $payment;
    
    public function _construct() {
        parent::_construct();
        $this->setTemplate('payuplpro/info.phtml');
    }

    /**
     * @return Orba_Payuplpro_Model_Config
     */
    protected function getConfig() {
        return Mage::getSingleton('payuplpro/config');
    }

    protected function getOrder() {
        if (!$this->order) {
            $this->order = $this->getInfo()->getOrder();
        }
        return $this->order;
    }
    
    protected function getQuote() {
        if (!$this->quote) {
            $this->quote = $this->getInfo()->getQuote();
        }
        return $this->quote;
    }
    
    protected function getPayment() {
        if (!$this->payment) {
            $order = $this->getOrder();
            if ($order) {
                $this->payment = $order->getPayment();
            } else {
                $quote = $this->getQuote();
                if ($quote) {
                    $this->payment = $quote->getPayment();
                }
            }
        }
        return $this->payment;
    }
    
    protected function setResult() {
        $payment = $this->getPayment();
        $order = $this->getOrder();
        /** @var Orba_Payuplpro_Model_Payment $_payment */
        $_payment = Mage::getSingleton('payuplpro/payment');
        $store_id = $order->getStoreId();
        $data = array(
            'pos_id' => $this->getConfig()->getPosId($store_id),
            'session_id' => $_payment->getTransactionSessionId($payment)
        );
        $res = $_payment->postBack($data, $store_id);
        $this->result = $res;
    }
    
    protected function getResult() {
        if (!$this->result) {
            $this->setResult();
        }
        return $this->result;
    }
    
    public function getSpecificInformation() {
        /** @var Orba_Payuplpro_Model_Payment $_payment */
        $_payment = Mage::getSingleton('payuplpro/payment');
        $order = $this->getOrder();
        if ($order) {
            $res = $this->getResult();
            if ($res['res']) {
                $paytypes = $_payment->getPaytypes($order->getStoreId());
                $trans = $res['xml']->trans;
                return array(
                    $this->__('Pay type') => $paytypes[(string)$trans->pay_type]['name'],
                    $this->__('Status') => Orba_Payuplpro_Model_Payment::$statuses[(int)$trans->status]
                );
            } else {
                if ($this->isAdminStore()) {
                    $code = $res['xml'] ? (int)$res['xml']->error->nr : false;
                    if ($code) {
                        return array(
                            $this->__('Transaction error') => $code.' - '.Orba_Payuplpro_Model_Payment::$errors[$code]
                        );
                    } else {
                        return array(
                            $this->__('Error') => 'Unable to connect to Payu.pl'
                        );
                    }
                } else {
                    $payment = $this->getPayment();
                    $paytype = $payment->getAdditionalInformation('payuplpro_paytype');
                    if ($paytype) {
                        $paytypes = $_payment->getPaytypes($order->getStoreId());
                        return array(
                            $this->__('Pay type') => $paytypes[$paytype]['name'],
                        );
                    }
                }
            }
        } else {
            $quote = $_payment->getQuote();
            $payment = $this->getPayment();
            $paytype = $_payment->getPaytype($payment);
            if ($paytype) {
                $paytypes = $_payment->getPaytypes($quote->getStoreId());
                return array(
                    $this->__('Pay type') => $paytypes[$paytype]['name'],
                );
            }
        }
        return array();
    }
    
    protected function isAdminStore() {
        return (int)Mage::app()->getStore()->getStoreId() === 0;
    }
    
    public function canBePaidOutside() {
        $_payment = Mage::getSingleton('payuplpro/payment');
        $res = $this->getResult();
        $order = $this->getOrder();
        if ($res['res']) {
            $trans = $res['xml']->trans;
            $status = (int)$trans->status;
            if ($status != Orba_Payuplpro_Model_Payment::PAYMENTSTATE_COMPLETED && !$_payment->isPaymentCompleted($order)) {
                return true;
            }
        } else {
            if (!$_payment->isPaymentCompleted($order)) {
                return true;
            }
        }
        return false;
    }
    
    public function isPaidOutside() {
        $_payment = Mage::getSingleton('payuplpro/payment');
        $order = $this->getOrder();
        return $_payment->isPaymentCompletedOutside($order);
    }
    
    public function isCompletedButBlocked() {
        $_payment = Mage::getSingleton('payuplpro/payment');
        $res = $this->getResult();
        $order = $this->getOrder();
        if ($res['res']) {
            $trans = $res['xml']->trans;
            $status = (int)$trans->status;
            if ($status == Orba_Payuplpro_Model_Payment::PAYMENTSTATE_COMPLETED && !$_payment->isPaymentCompleted($order)) {
                return true;
            }
        }
        return false;
    }
    
    public function getOutsideUrl() {
        return Mage::helper("adminhtml")->getUrl('adminhtml/payuplpro_payment/outside', array('order_id' => $this->getInfo()->getOrder()->getId()));
    }
    
    public function getUnblockUrl() {
        return Mage::helper("adminhtml")->getUrl('adminhtml/payuplpro_payment/unblock', array('order_id' => $this->getInfo()->getOrder()->getId()));
    }
    
    public function getPaymentUrl() {
        /** @var Orba_Payuplpro_Helper_Data $payuplproHelper */
        $payuplproHelper  = Mage::helper('payuplpro');
        /** @var Orba_Payuplpro_Model_Payment $_payment */
        $_payment = Mage::getSingleton('payuplpro/payment');

        $order = $this->getOrder();
        if (!$this->getParentBlock() && !$this->getConfig()->shouldShowLinkInEmail()) {
            return false;
        }
        if ($order && $_payment->isPaymentAvailable($order)) {
            return $payuplproHelper->getPayPaymentUrl($order);
        } else {
            return false;
        }
    }
	
};