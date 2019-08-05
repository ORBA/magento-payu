<?php
class Orba_Payuplpro_Adminhtml_Payuplpro_PaymentController extends Mage_Adminhtml_Controller_Action {

    protected function _isAllowed() {
        $session = Mage::getSingleton('admin/session');
        return $session->isAllowed('sales/payuplpro');
    }

    /**
     * Mark transaction as completed outside of Payu (eg. standard bank transfer).
     */
	public function outsideAction() {
        /**
         * @var $_payment Orba_Payuplpro_Model_Payment
         */
        $order_id = $this->getRequest()->getParam('order_id');
        if ($order_id) {
            $order = Mage::getModel('sales/order')->load($order_id);
            if ($order->getId()) {
                $_payment = Mage::getSingleton('payuplpro/payment');
                $payment = $order->getPayment();
                if ($_payment->paidOutside($payment)) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('payuplpro')->__('The transaction has been completed.'));
                } else {
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('payuplpro')->__('An error occurred while saving the transaction.'));
                }
            }
        }
        $this->_redirectReferer();
    }

    /**
     * Unblock order if it accidentally stays in wrong state but the transaction was completed using Payu.
     */
    public function unblockAction() {
        /**
         * @var $_payment Orba_Payuplpro_Model_Payment
         */
        $order_id = $this->getRequest()->getParam('order_id');
        if ($order_id) {
            $order = Mage::getModel('sales/order')->load($order_id);
            if ($order->getId()) {
                $_payment = Mage::getSingleton('payuplpro/payment');
                $payment = $order->getPayment();
                if ($_payment->unblock($payment)) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('payuplpro')->__('The order has been unblocked.'));
                } else {
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('payuplpro')->__('An error occurred while unblocking the order.'));
                }
            }
        }
        $this->_redirectReferer();
    }
    
}