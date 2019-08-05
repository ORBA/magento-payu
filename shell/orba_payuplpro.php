<?php

require_once 'abstract.php';

class Shell_Orba_Payuplpro extends Mage_Shell_Abstract
{

    /**
     * Run script
     */
    public function run()
    {
        $store = $this->getArg("store");
        if ($store) {
            $storeId = Mage::app()->getStore()->getId();
            $appEmulation = Mage::getSingleton('core/app_emulation');
            $appEmulation->startEnvironmentEmulation($storeId);
        }

        /**
         * Simulate sending from PayU data about changes in transaction.
         */
        if ($this->getArg('simulation')) {
            echo "Simulation start\r\n";
            $data = $this->_getSimulatedData();
            /** @var Orba_Payuplpro_Model_Payment $payuplproPaymentModel */
            $payuplproPaymentModel = Mage::getModel("payuplpro/payment");
            $result = $payuplproPaymentModel->processPaymentStateUpdate($data);
            echo $result ? "OK" : "ERROR";
            echo "\r\nSimulation end\r\n";
            return;
        }

        /**
         * Retrieve payment info for order
         */
        if ($this->getArg('info')) {
            $incrementOrderId = $this->getArg("order");
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->loadByIncrementId($incrementOrderId);

            /** @var Orba_Payuplpro_Model_Payment $_payment */
            $_payment = Mage::getSingleton('payuplpro/payment');
            $currentSessionId = $_payment->getTransactionSessionId($order->getPayment());

            $data = $this->_getSimulatedData();
            $data['session_id'] = $currentSessionId;

            $res = $_payment->postBack($data, $order->getStoreId());
            print_r($res);
            return;
        }

        /**
         * Force state (impersonation as PayU)
         * Inject preparated data about changes in transaction.
         */
        if ($this->getArg('force_status')) {
            echo "Force status start\r\n";
            $data = $this->_getSimulatedData();
            /** @var Orba_Payuplpro_Model_Payment $payuplproPaymentModel */
            $payuplproPaymentModel = Mage::getModel("payuplpro/payment");

            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->loadByIncrementId($this->getArg("order"));

            $forceData = array(
                'session_id' => $this->getArg("session"),
                'status'     => $this->getArg("status"),
                'order_id'   => $this->getArg("order"),
                'amount'     => round($order->getGrandTotal() * 100, 2));

            $result = $payuplproPaymentModel->processPaymentStateUpdate($data, $forceData);
            echo $result ? "OK" : "ERROR";
            echo "\r\nForce status end\r\n";
            return;
        }

        die($this->usageHelp());
    }

    /**
     * @return array
     */
    protected function _getSimulatedData()
    {
        $pos       = $this->getArg("pos");
        $sessionId = $this->getArg("session");
        $data = array(
            'pos_id'     => $pos,
            'session_id' => $sessionId
        );
        return $data;
    }

    /**
     * Retrieve usage help message
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:    php -f orba_payuplpro.php -- [options]
Example:  php -f orba_payuplpro.php simulation
  help                            This help
  -h                              Short alias for help
  
  --store <storeId>               Emulate store. <storeId> can by integer or store code. Default
  
  simulation                      Make simulation for payment
  --session <sessionId>           PayU session_id
  --pos <posId>                   PayU pos_id
  example                         php -f orba_payuplpro.php simulation --session b146c19e25385e992f7743b51a9f335a-203-1 --pos 249451

  info                            Retrieve payment info for order
  --order <incrementId>           Magento incremented ID
  --pos <posId>                   PayU pos_id
  example                         php -f orba_payuplpro.php info --order 145000014 --pos 249451

  force_status
  --pos <posId>                   PayU pos_id
  --order <incrementId>           Magento incremented ID
  --session <sessionId>           PayU session_id
  --status <statusCode>           see:
                                      PAYMENTSTATE_NEW        = '1';
                                      PAYMENTSTATE_CANCELED   = '2';
                                      PAYMENTSTATE_DENIED     = '3';
                                      PAYMENTSTATE_INPROGRSSS = '4';
                                      PAYMENTSTATE_PENDING    = '5';
                                      PAYMENTSTATE_REVERSED   = '7';
                                      PAYMENTSTATE_COMPLETED  = '99';
                                      PAYMENTSTATE_ERROR      = '888';
  example                         php -f orba_payuplpro.php force_status --order 145000040 --session a637f7951a2bf6428cb86f3283530d61-229-1 --status 3 --pos 249451


USAGE;
    }
}

$shell = new Shell_Orba_Payuplpro();
$shell->run();