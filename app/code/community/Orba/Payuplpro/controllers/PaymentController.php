<?php

/**
 * Class Orba_Payuplpro_PaymentController
 *
 * @property Mage_Checkout_Model_Session         _session
 * @property Mage_Sales_Model_Order              _order
 * @property Mage_Sales_Model_Order_Payment      _payment
 */
class Orba_Payuplpro_PaymentController extends Mage_Core_Controller_Front_Action {

    /**
     * @var Mage_Checkout_Model_Session
     */
    protected $_session = null;

    /**
     * @var Mage_Sales_Model_Order
     */
    protected $_order = null;

    /**
     * @var Mage_Sales_Model_Order_Payment
     */
    protected $_payment = null;

    /**
     * @return Orba_Payuplpro_Model_Config
     */
    protected function getConfig() {
        return Mage::getSingleton('payuplpro/config');  
    }


    /**
     * Initiates new payment
     *
     * Flow:
     * Checkout after place an order redirect to
     * @see Orba_Payuplpro_Model_Payment::getOrderPlaceRedirectUrl()
     * Then this function prepare data to send them into PayU
     * After send customer lands on PayU payment page
     */
    public function newAction() {
        $this->setSession();
        $this->setOrder();
        $this->forceNewOrderStatus();
        $this->setPayment(true);
        $this->setTryNumber();
        if (!$this->_order->getEmailSent() == 1) {
            $this->_order->sendNewOrderEmail();
        }
        $this->_renderRedirectPage();
    }

    /**
     * Redirect to PayU payment page
     * It's prepare data to send them into PayU
     */
    public function payAction()
    {
        try {
            $orderId = $this->getRequest()->getParam('id');
            $hash = $this->getRequest()->getParam('hash');
            $this->setSession();
            $this->setOrder($orderId);
            $this->_payment = $this->_order->getPayment();
            $this->checkHash($hash);
            // First time
            if ($this->_payment->getAdditionalInformation('payuplpro_try_number') == 0) {
                $this->forceNewOrderStatus(true);
                $this->setPayment(true);
            }
            $this->_forward('repeat', null, null, array(
                'id' => $orderId,
                'hash' => $hash
            ));
        } catch (Mage_Core_Exception $e) {
            Mage::log('Error with pay link: ' . $e->getMessage(), null, 'payuplpro.log');
            $this->_redirect('/');
        }
    }

    /* initiates new payment from "try again" link */
    public function repeatAction() {
        $params = $this->getRequest()->getParams();
        $this->setSession();
        $this->setOrder($params['id']);
        $this->setPayment();
        $this->checkHash($params['hash']);
        if (Mage::getSingleton('payuplpro/payment')->isPaymentAvailable($this->_order)) {
            $this->setTryNumber();
            if ($this->_order->canUnhold()) {
                $this->_order->unhold()->save();
            }
            $this->_renderRedirectPage();
        } else {
            $this->_redirect('sales/order/view', array('order_id' => $this->_order->getId())); 
        }
    }

    /**
     * Page with populated data form
     * @see Orba_Payuplpro_Block_Redirect
     * @see app/design/frontend/base/default/template/payuplpro/redirect.phtml
     *
     * @param Mage_Sales_Model_Order $order
     */
    protected function _renderRedirectPage($order = null)
    {
        if (!$order) $order = $this->_order;
        $this->loadLayout();
        $this->getLayout()->getBlock('payuplpro_child')->setOrder($order);
        $this->renderLayout();
    }

    /* handles successful payment redirect */
    public function okAction() { 
        $this->setSession();
        $this->setOrder($this->getOrderIdFromResponse());
        $this->setPayment();
        $this->checkHash($this->getPaymentHashFromResponse());
        $this->_session->getQuote()->setIsActive(false)->save();
        if ($this->isNewOrder()) {
            $this->_redirect('checkout/onepage/success', array('_secure'=>true)); 
        } else {
            $this->loadLayout();
            $this->renderLayout(); 
        } 
    }
    
    /* handles failed payment redirect*/
    public function errorAction() {
        $this->setSession();
        $this->setOrder($this->getOrderIdFromResponse());
        $this->setPayment();
        $this->checkHash($this->getPaymentHashFromResponse());
        $params = $this->getRequest()->getParams();
        if (array_key_exists('code', $params)) {
            Mage::log('Error: Order id - '.$this->_order->getIncrementId().', Code - '.$params['code'], null, 'payuplpro.log');
        }
        /** @var Orba_Payuplpro_Model_Payment $paymentModel */
        $paymentModel = Mage::getModel('payuplpro/payment');
        if (!$paymentModel->isPaymentCompleted($this->_order) && !$paymentModel->isOrderCompleted($this->_order)) {
            $state = Mage_Sales_Model_Order::STATE_HOLDED;
            $store_id = $this->_order->getStoreId();
            $this->_order
                ->setHoldBeforeState($this->_order->getState())
                ->setHoldBeforeStatus($this->_order->getStatus())
                ->setState($state, $this->getConfig()->getDefaultStatus($state, $store_id))
                ->save();
            $this->_session->setErrorMessage($this->__('Your transaction was rejected by Payu.pl.').' '.$this->__('Click <a href="%s">here</a> to pay again.', Mage::helper('payuplpro')->getRepeatPaymentUrl($this->_order)));
            $this->_session->getQuote()->setIsActive(false)->save();
        }
        if ($this->isNewOrder()) {
            $this->_redirect('checkout/onepage/failure', array('_secure'=>true));
        } else {
            $this->loadLayout();
            $this->renderLayout();
        }
    }
    
    /*handles payment status update - called by Payu.pl*/
    public function onlineAction() { 
        $error = false;
        $this->loadLayout();
        try {
            $data = $this->getRequest()->getPost();
            if (Mage::getModel('payuplpro/payment')->processPaymentStateUpdate($data)) {
                $this->getLayout()->getBlock('payuplpro_child')->setMessage('OK');
            } else {
                $error = true;
            }
        } catch (Exception $e) {
            Mage::logException($e);
            $error = true;
        }
        if ($error) {
            $this->getLayout()->getBlock('payuplpro_child')->setMessage('ERROR');
        }
        $this->renderLayout();
    }
    
    protected function isNewOrder() {
        return (Mage::getSingleton('checkout/session')->getLastRealOrderId() == $this->_order->getRealOrderId());
    }

    protected function getOrderIdFromResponse() {
        $params = $this->getRequest()->getParams();
        $session = explode('-', $params['sid']);
        return $session[1];
    }
    
    protected function getPaymentHashFromResponse() {
        $params = $this->getRequest()->getParams();
        $session = explode('-', $params['sid']);
        return $session[0];
    }
    
    protected function setSession() {
        $this->_session = Mage::getSingleton('checkout/session');
        $this->_session->setQuoteId($this->_session->getPayuplproQuoteId(true));
    }
    
    protected function setOrder($id = null) {
        if ($id == null) {
            $id = $this->_session->getLastRealOrderId();
            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($id);
        } else {
            $this->_order = Mage::getModel('sales/order')->load($id);
        }
        if (!$this->_order->getId()) {
            Mage::throwException("Invalid order number: {$id}");
        }
    }
    
    protected function checkHash($hash) {
        if ($hash) {
            $payment_hash = $this->_payment->getAdditionalInformation('payuplpro_hash');
            if (!$payment_hash) {
                $payment_hash = $this->_payment->getAdditionalInformation('payuplpro_customer_sid');
            }
            if ($hash == $payment_hash) {
                return true;
            }
        }
        Mage::throwException("Invalid payment hash.");
    }
    
    protected function setPayment($is_order_new = false) {
        $this->_payment = $this->_order->getPayment();
        $save = false;
        if ($is_order_new) {
            $this->_payment->setAdditionalInformation('payuplpro_is_completed', false);
            $save = true;
        }
        if (!$this->_payment->getAdditionalInformation('payuplpro_hash')) {
            $this->_payment->setAdditionalInformation('payuplpro_hash', Mage::helper('payuplpro')->getHash());
            $save = true;
        }
        if ($save) {
            $this->_payment->save();
        }
    }
    
    protected function setTryNumber() {
        $try_number = (int)$this->_payment->getAdditionalInformation('payuplpro_try_number') + 1;
        $this->_payment->setAdditionalInformation('payuplpro_try_number', $try_number);
        $this->_payment->save();
    }

    protected function forceNewOrderStatus($skipSessionCheck = false) {
        if ($this->isNewOrder() || $skipSessionCheck) {
            $state_old = $this->_order->getState();
            if ($state_old === Mage_Sales_Model_Order::STATE_NEW) {
                $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
                $store_id = $this->_order->getStoreId();
                $this->_order->setState($state, $this->getConfig()->getDefaultStatus($state, $store_id))
                    ->save();
            }
        }
    }
    
    public function cancelAction() {
        $params = $this->getRequest()->getParams();
        $order_id = $params['order_id'];
        $payment = Mage::getModel('sales/order')->loadByIncrementId($order_id)->getPayment();
        if (Mage::getModel('payuplpro/payment')->isCancellationEnabled($payment)) {
            Mage::getModel('payuplpro/payment')->cancelTransaction($payment);
        }
    }
	
}