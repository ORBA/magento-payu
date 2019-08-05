<?php

class Orba_Payuplpro_Model_Payment extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'payuplpro';
    protected $_formBlockType = 'payuplpro/form';
    protected $_infoBlockType = 'payuplpro/info';
    protected $_isInitializeNeeded = true;
    protected $_canUseInternal = true;
    protected $_canUseForMultishipping = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $refund_soap_client = null;
    protected $paytypes = null;
    public static $refund_results = array(
        0 => 'Uznanie wykonane prawidłowo.',
        -1 => 'Brak autoryzacji.',
        -2 => 'Brak transakcji o podanym identyfikatorze.',
        -3 => 'Transakcja nie została jeszcze zakooczona i nie można dla niej wykonad uznania.',
        -4 => 'Brak środków do wykonania uznania.',
        -5 => 'Podana kwota uznania jest zbyt duża i nie można wykonad uznania.',
        -6 => 'Podana kwota uznania jest zbyt mała i nie można wykonad uznania.',
        -7 => 'Podany typ uznania nie jest obsługiwany.',
        -8 => 'Uznania dla transakcji są wykonywane zbyt często. Uznanie dla transakcji może być wykonane maksymalnie raz na 10 minut.',
        -9 => 'Przesłany podpis (refundsHash) jest niepoprawny. W międzyczasie mogły wystąpić inne uznania i należy jeszcze raz pobrać informacje o uznaniach.',
        -10 => 'Cała kwota transakcji została juz zwrócona.',
        -99 => 'Nieokreślony błąd.'
    );
    public static $errors = array(
        100 => 'brak lub błędna wartość parametru pos_id',
        101 => 'brak parametru session_id',
        102 => 'brak parametru ts',
        103 => 'brak lub błędna wartość parametru sig',
        104 => 'brak parametru desc',
        105 => 'brak parametru client_ip',
        106 => 'brak parametru first_name',
        107 => 'brak parametru last_name',
        108 => 'brak parametru street',
        109 => 'brak parametru city',
        110 => 'brak parametru post_code',
        111 => 'brak parametru amount (lub/oraz amount_netto dla usługi SMS)',
        112 => 'błędny numer konta bankowego',
        113 => 'brak parametru email',
        114 => 'brak numeru telefonu',
        200 => 'inny chwilowy błąd',
        201 => 'inny chwilowy błąd bazy danych',
        202 => 'POS o podanym identyfikatorze jest zablokowany',
        203 => 'niedozwolona wartość pay_type dla danego pos_id',
        204 => 'podana metoda płatności (wartość pay_type) jest chwilowo zablokowana dla danego pos_id, np. przerwa konserwacyjna bramki płatniczej',
        205 => 'kwota transakcji mniejsza od wartości minimalnej',
        206 => 'kwota transakcji większa od wartości maksymalnej',
        207 => 'przekroczona wartość wszystkich transakcji dla jednego klienta w ostatnim przedziale czasowym',
        208 => 'POS działa w wariancie ExpressPayment lecz nie nastąpiła aktywacja tego wariantu współpracy (czekamy na zgodę działu obsługi klienta)',
        209 => 'błędny numer pos_id lub pos_auth_key',
        500 => 'transakcja nie istnieje',
        501 => 'brak autoryzacji dla danej transakcji',
        502 => 'transakcja rozpoczęta wcześniej',
        503 => 'autoryzacja do transakcji była już przeprowadzana',
        504 => 'transakcja anulowana wcześniej',
        505 => 'transakcja przekazana do odbioru wcześniej',
        506 => 'transakcja już odebrana',
        507 => 'błąd podczas zwrotu środków do klienta',
        599 => 'błędny stan transakcji, np. nie można uznać transakcji kilka razy lub inny, prosimy o kontakt',
        999 => 'inny błąd krytyczny - prosimy o kontakt'
    );
    public static $statuses = array(
        1 => 'nowa',
        2 => 'anulowana',
        3 => 'odrzucona',
        4 => 'rozpoczęta',
        5 => 'oczekuje na odbiór',
        7 => 'płatność odrzucona, otrzymano środki od klienta po wcześniejszym anulowaniu transakcji, lub nie było możliwości zwrotu środków w sposób automatyczny, sytuacje takie będą monitorowane i wyjaśniane przez zespół PayU',
        99 => 'płatność odebrana - zakończona',
        888 => 'błędny status - prosimy o kontakt'
    );

    /* payu.pl payment state */

    const PAYMENTSTATE_NEW = '1';
    const PAYMENTSTATE_CANCELED = '2';
    const PAYMENTSTATE_DENIED = '3';
    const PAYMENTSTATE_INPROGRSSS = '4';
    const PAYMENTSTATE_PENDING = '5';
    const PAYMENTSTATE_REVERSED = '7';
    const PAYMENTSTATE_COMPLETED = '99';
    const PAYMENTSTATE_ERROR = '888';
    const POLISH_ZLOTY_CODE = 'PLN';
    const PAYMENT_TYPE_CARD = 'c';
    const PAYMENT_TYPE_TRADITIONAL = 'b';
    const PAYMENT_TYPE_PAYU = 'pu';
    const PAYMENT_TYPE_INSTALMENTS = 'ai';
    const PAYMENT_TYPE_TEST = 't';
    const TEST_PAYMENT_MIN = 0.5;
    const TEST_PAYMENT_MAX = 1000;

    /**
     * Return information if Payu payment method can be used in checkout.
     * Returns false if there is a problem with connection to Payu or if quote total is too small or too large for all paytypes.
     * 
     * @return boolean
     */
    public function canUseCheckout() {
        $paytypes = $this->getPaytypes();
        if ($paytypes) {
            $quote = $this->getQuote();
            $total = $this->getQuoteTotalInPolishZlotys($quote);
            foreach ($paytypes as $paytype) {
                if ($paytype['enable'] && $paytype['min'] <= $total && $paytype['max'] >= $total) {
                    return parent::canUseCheckout();
                }
            }
        }
        return false;
    }

    /**
     * Gets payuplpro session namespace.
     *
     * @return Orba_Payuplpro_Model_Session
     */
    public function getSession() {
        return Mage::getSingleton('payuplpro/session');
    }

    /**
     * Gets checkout session namespace.
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Gets URL to redirect after checkout.
     * 
     * @return string
     */
    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('payuplpro/payment/new', array('_secure' => true));
    }

    /**
     * Gets current quote.
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote() {
        return $this->getCheckout()->getQuote();
    }

    /**
     * Gets config model.
     * 
     * @return Orba_Payuplpro_Model_Config
     */
    public function getConfig() {
        return Mage::getSingleton('payuplpro/config');
    }

    protected function getRedirectSig($data) {
        $md5Key = $this->getConfig()->getMD5Key1();
        $sig = md5(
                $data['pos_id'] .
                ((isset($data['pay_type'])) ? $data['pay_type'] : '') .
                $data['session_id'] .
                $data['pos_auth_key'] .
                $data['amount'] .
                $data['desc'] .
                $data['desc2'] .
                $data['order_id'] .
                $data['first_name'] .
                $data['last_name'] .
                $data['street'] .
                $data['city'] .
                $data['post_code'] .
                $data['country'] .
                $data['email'] .
                $data['phone'] .
                $data['language'] .
                $data['client_ip'] .
                $data['ts'] .
                $md5Key
        );
        return $sig;
    }

    public function getTransactionSessionId($payment) {
        $hash = $payment->getAdditionalInformation('payuplpro_hash');
        if (!$hash) {
            $hash = $payment->getAdditionalInformation('payuplpro_customer_sid');
        }
        $order_id = $payment->getParentId();
        $try_number = $payment->getAdditionalInformation('payuplpro_try_number');
        return $hash . '-' . $order_id . '-' . $try_number;
    }

    public function getRedirectData($order) {
        $payment = $order->getPayment();
        $billing = $order->getBillingAddress();
        $order_id = $order->getId();
        $increment_order_id = $order->getRealOrderId();
        $try_number = $payment->getAdditionalInformation('payuplpro_try_number');
        $r_data = array(
            "pos_id" => $this->getConfig()->getPosId(),
            "pos_auth_key" => $this->getConfig()->getPosAuthKey(),
            "session_id" => $this->getTransactionSessionId($payment),
            "amount" => $this->getOrderTotalInPolishCents($order),
            "desc" => Mage::helper('payuplpro')->__("Order no %s", $increment_order_id),
            "desc2" => 'Orba_Payuplpro',
            "order_id" => $increment_order_id,
            "first_name" => $billing->getFirstname(),
            "last_name" => $billing->getLastname(),
            "street" => preg_replace("/\s/", " ", $billing->getStreetFull()),
            "city" => $billing->getCity(),
            "post_code" => $billing->getPostcode(),
            "country" => $billing->getCountry(),
            "email" => $order->getCustomerEmail(),
            "phone" => $billing->getTelephone(),
            "language" => $this->_getLanguageFromLocale(Mage::app()->getLocale()->getLocaleCode()),
            "client_ip" => $_SERVER['REMOTE_ADDR'],
            "ts" => time() * rand(1, 10)
        );
        if ($try_number == 1) {
            $pay_type = $payment->getAdditionalInformation('payuplpro_paytype');
            if (!empty($pay_type)) {
                $r_data['pay_type'] = $pay_type;
            }
        }
        $r_data['sig'] = $this->getRedirectSig($r_data);
        return $r_data;
    }

    //process payment update
    protected $_remotePaymentDataXML;
    protected $_order;

    /**
     * Updates payment and order objects basing on request from Payu.
     * 
     * @param array $request
     * @param array $force
     * @return boolean
     */
    public function processPaymentStateUpdate(array $request, $force = array()) {
        try {
            $res = $this->postBack($request);
            if (!$res['res'] && empty($force)) {
                return false;
            } else {
                $this->_remotePaymentDataXML = $res['xml'];
            }
            if (!empty($force)) {
                foreach ($force as $key => $value) {
                    $this->_remotePaymentDataXML->trans[0]->{$key}[0] = $value;
                }
            }

            //remote payment info 
            $sessionId = (string) $this->_remotePaymentDataXML->trans[0]->session_id[0];
            $remoteState = (string) $this->_remotePaymentDataXML->trans[0]->status[0];
            $incrementOrderId = (string) $this->_remotePaymentDataXML->trans[0]->order_id[0];

            //local payment and order infor
            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($incrementOrderId);
            /** @var Mage_Sales_Model_Order_Payment $lPayment */
            $lPayment = $this->_order->getPayment();
            if ($lPayment == null) {
                throw new Exception('No payment exists for given order. ' . $request['session_id']);
            }

            $messageOnly = !$this->_isTransactionCurrent($lPayment, $sessionId) || $this->isOrderCompleted($this->_order);
            // Override if there is an old transaction and
            // configuration is set for processing also old completed transaction
            if (!$this->_isTransactionCurrent($lPayment, $sessionId) &&      // is old
                $this->getConfig()->isProcessingOldPaidTransactionsEnabled() // is enabled
            ) {
                if ($remoteState == self::PAYMENTSTATE_COMPLETED) {
                    // If order not completed process it
                    $messageOnly = $this->isOrderCompleted($this->_order);
                } else {
                    // Don't process
                    $messageOnly = true;
                }
            }

            $localState = $lPayment->getAdditionalInformation('payuplpro_state');
            Mage::log('Payment state update: ' . $sessionId . ' ' . $remoteState . ($messageOnly ? ' message only' : ''), null, 'payuplpro.log');
            $this->_onPaymentStateChange($lPayment, (string) $remoteState, (string) $localState, $messageOnly);

            return true;
        } catch (Exception $e) {
            Mage::logException($e);
        }
        return false;
    }

    /**
     * Checks if Payu transaction with specified session id is current transaction for order.
     * 
     * @param type $payment
     * @param string $transactionId
     * @return boolean
     */
    protected function _isTransactionCurrent($payment, $transactionId) {
        $tryNumberLocale = (int) $payment->getAdditionalInformation('payuplpro_try_number');
        $transactionId = explode('-', $transactionId);
        $tryNumberRemote = (int) $transactionId[2];
        return $tryNumberLocale === $tryNumberRemote;
    }

    /* get payment state */

    public function postBack($data, $store_id = null) {
        $args = array(
            'pos_id' => $data['pos_id'],
            'session_id' => $data['session_id'],
            'ts' => now() * 1123
        );
        $md5Key = $this->getConfig()->getMD5Key1($store_id);
        $args['sig'] = md5($args['pos_id'] . $args['session_id'] . $args['ts'] . $md5Key);
        $url = $this->getConfig()->getGatewayUrl() . "/UTF/Payment/get/xml";

        try {
            $body = Mage::helper('payuplpro')->sendPost($url, $args);
            if (!$body) {
                throw new Exception('Cannot connect to ' . $url);
            }
            $res = array(
                'xml' => new Varien_Simplexml_Element($body)
            );
            if ((string) $res['xml']->status == 'OK') {
                $res['res'] = true;
            } else {
                $res['res'] = false;
            }
            return $res;
        } catch (Exception $e) {
            Mage::logException($e);
            return array(
                'res' => false,
                'xml' => false
            );
        }
    }

    protected function _onPaymentStateChange($pmnt, $newState, $oldState, $messageOnly) {
        if ($newState === $oldState) {
            $messageOnly = true;
        }
        switch ($newState) {
            case Orba_Payuplpro_Model_Payment::PAYMENTSTATE_NEW: $this->_processPaymentChangeNew($pmnt, $messageOnly);
                break;
            case Orba_Payuplpro_Model_Payment::PAYMENTSTATE_CANCELED: $this->_processPaymentChangeCanceled($pmnt, $messageOnly);
                break;
            case Orba_Payuplpro_Model_Payment::PAYMENTSTATE_DENIED: $this->_processPaymentChangeDenied($pmnt, $messageOnly);
                break;
            case Orba_Payuplpro_Model_Payment::PAYMENTSTATE_INPROGRSSS: $this->_processPaymentChangeInProgress($pmnt, $messageOnly);
                break;
            case Orba_Payuplpro_Model_Payment::PAYMENTSTATE_PENDING: $this->_processPaymentChangePending($pmnt, $messageOnly);
                break;
            case Orba_Payuplpro_Model_Payment::PAYMENTSTATE_REVERSED: $this->_processPaymentChangeReversed($pmnt, $messageOnly);
                break;
            case Orba_Payuplpro_Model_Payment::PAYMENTSTATE_COMPLETED: $this->_processPaymentChangeCompleted($pmnt, $messageOnly);
                break;
            case Orba_Payuplpro_Model_Payment::PAYMENTSTATE_ERROR: $this->_processPaymentChangeError($pmnt, $messageOnly);
                break;
            default: $this->_processPaymentChangeError($pmnt, $messageOnly);
                break;
        }
        if (!$messageOnly) {
            try {
                $this->setState($pmnt, $newState);
                if (!in_array($newState, array(Orba_Payuplpro_Model_Payment::PAYMENTSTATE_NEW, Orba_Payuplpro_Model_Payment::PAYMENTSTATE_PENDING))) {
                    $pmnt->setAdditionalInformation('payuplpro_online', true);
                    $pmnt->save();
                }
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }

    protected function _processPaymentChangeNew($p, $messageOnly) {
        $message = 'Komunikat z Payu.pl: Nowa transakcja rozpoczęta.';
        $this->_addPaymentOrderInternalComment($p, $message . ' [' . $this->_remotePaymentDataXML->trans[0]->session_id[0] . ', ' . Orba_Payuplpro_Model_Payment::PAYMENTSTATE_NEW . ']');
    }

    protected function _processPaymentChangeCanceled($p, $messageOnly) {
        $message = 'Komunikat z Payu.pl: Transakcja anulowana.';
        $this->_addPaymentOrderInternalComment($p, $message . ' [' . $this->_remotePaymentDataXML->trans[0]->session_id[0] . ', ' . Orba_Payuplpro_Model_Payment::PAYMENTSTATE_CANCELED . ']');
        if (!$messageOnly) {
            $this->_changePaymentOrderState($p, Mage_Sales_Model_Order::STATE_HOLDED, $message);
        }
    }

    protected function _processPaymentChangeDenied($p, $messageOnly) {
        $message = 'Komunikat z Payu.pl: Transakcja odrzucona.';
        $this->_addPaymentOrderInternalComment($p, $message . ' [' . $this->_remotePaymentDataXML->trans[0]->session_id[0] . ', ' . Orba_Payuplpro_Model_Payment::PAYMENTSTATE_DENIED . ']');
        if (!$messageOnly) {
            $this->_changePaymentOrderState($p, Mage_Sales_Model_Order::STATE_HOLDED, $message);
        }
    }

    protected function _processPaymentChangeInProgress($p, $messageOnly) {
        $message = 'Komunikat z Payu.pl: Transakcja w trakcie realizacji.';
        $this->_addPaymentOrderInternalComment($p, $message . ' [' . $this->_remotePaymentDataXML->trans[0]->session_id[0] . ', ' . Orba_Payuplpro_Model_Payment::PAYMENTSTATE_INPROGRSSS . ']');
    }

    protected function _processPaymentChangePending($p, $messageOnly) {
        $message = 'Komunikat z Payu.pl: Transakcja oczekuje na zatwierdzenie.';
        $this->_addPaymentOrderInternalComment($p, $message . ' [' . $this->_remotePaymentDataXML->trans[0]->session_id[0] . ', ' . Orba_Payuplpro_Model_Payment::PAYMENTSTATE_PENDING . ']');
    }

    protected function _processPaymentChangeReversed($p, $messageOnly) {
        $message = 'Komunikat z Payu.pl: Transakcja zwrócona.';
        $this->_addPaymentOrderInternalComment($p, $message . ' [' . $this->_remotePaymentDataXML->trans[0]->session_id[0] . ', ' . Orba_Payuplpro_Model_Payment::PAYMENTSTATE_REVERSED . ']');
        if (!$messageOnly) {
            $this->_changePaymentOrderState($p, Mage_Sales_Model_Order::STATE_HOLDED, $message);
        }
    }

    protected function _processPaymentChangeCompleted($p, $messageOnly) {
        $message = 'Komunikat z Payu.pl: Transakcja zakończona pomyślnie.';
        $this->_addPaymentOrderInternalComment($p, $message . ' [' . $this->_remotePaymentDataXML->trans[0]->session_id[0] . ', ' . Orba_Payuplpro_Model_Payment::PAYMENTSTATE_COMPLETED . ']');
        if (!$messageOnly) {
            if ($this->getConfig()->getCreateOrderInvoice()) {
                $p->setTransactionId((string) $this->_remotePaymentDataXML->trans[0]->session_id[0])
                        ->registerPayuplproCaptureNotification($this->_remotePaymentDataXML->trans[0]->amount / 100)
                        ->setIsTransactionApproved(true)
                        ->setIsTransactionClosed(true)
                        ->setAdditionalInformation('payuplpro_is_completed', true)
                        ->save();
            } else {
                $p->setAdditionalInformation('payuplpro_is_completed', true)
                        ->save();
            }
            $this->_changePaymentOrderState($p, Mage_Sales_Model_Order::STATE_PROCESSING, $message);
        }
    }

    protected function _processPaymentChangeError($p, $messageOnly) {
        $message = 'Komunikat z Payu.pl: Transakcja błędna.';
        $this->_addPaymentOrderInternalComment($p, $message . ' [' . $this->_remotePaymentDataXML->trans[0]->session_id[0] . ', ' . Orba_Payuplpro_Model_Payment::PAYMENTSTATE_ERROR . ']');
        if (!$messageOnly) {
            $this->_changePaymentOrderState($p, Mage_Sales_Model_Order::STATE_HOLDED, $message);
        }
    }

    /**
     * Updates payment order state and sets default status configured in module's settings form in admin panel.
     * Sets order update comment and sends e-mail to customer.
     * 
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param string $state
     * @param string $comment
     */
    protected function _changePaymentOrderState($payment, $state, $comment) {
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();
        if (Mage_Sales_Model_Order::STATE_HOLDED === $state) {
            $order
                ->setHoldBeforeState($order->getState())
                ->setHoldBeforeStatus($order->getStatus());
        }
        $order->setState($state, $this->getConfig()->getDefaultStatus($state, $storeId), $comment, true)
                ->sendOrderUpdateEmail(true, $comment)
                ->save();
    }

    /**
     * Adds internal comment (customer isn't notified) to the payment order without state or status change.
     * 
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param string $comment
     */
    protected function _addPaymentOrderInternalComment($payment, $comment) {
        $order = $payment->getOrder();
        $order->addStatusToHistory($order->getStatus(), $comment, false)
                ->save();
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function isPaymentCompleted($order) {
        $data = $order->getPayment()->getAdditionalInformation();
        $is_completed = isset($data['payuplpro_is_completed']) ? $data['payuplpro_is_completed'] : false;
        return $is_completed;
    }

    public function isOrderCompleted($order) {
        $state = $order->getState();
        return in_array($state, array(Mage_Sales_Model_Order::STATE_CLOSED, Mage_Sales_Model_Order::STATE_CANCELED, Mage_Sales_Model_Order::STATE_COMPLETE));
    }

    /**
     * Checks if payment is available, ie. is it incomplete and has pending payment or holded status.
     * 
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function isPaymentAvailable($order) {
        $status = $order->getStatus();
        $state  = $order->getState();
        if (Mage_Sales_Model_Order::STATE_NEW == $state) {
            return true;
        }
        return (!$this->isPaymentCompleted($order) && (in_array($status, array(Mage::getStoreConfig("payment/payuplpro/status_pending_payment"), Mage::getStoreConfig("payment/payuplpro/status_holded"), Mage_Sales_Model_Order::STATE_HOLDED))));
    }

    protected function _getLanguageFromLocale($locale) {
        if ($locale == 'pl_PL') {
            return 'pl';
        } else {
            return 'en';
        }
    }

    public function getPaytypes($store_id = null) {
        if (!$this->paytypes) {
            $results = array();
            $xml_url = $this->getConfig()->getGatewayUrl($store_id) . '/UTF/xml/' . $this->getConfig()->getPosId($store_id) . '/' . substr($this->getConfig()->getMD5Key1($store_id), 0, 2) . '/paytype.xml';
            try {
                $body = Mage::helper('payuplpro')->sendRequest($xml_url);
                if (!$body) {
                    throw new Exception('Cannot connect to ' . $xml_url);
                }
                $xml = new Varien_Simplexml_Element($body);
                $results = array();
                foreach ($xml as $node) {
                    $data = array(
                        'type' => (string) $node->type,
                        'name' => (string) $node->name,
                        'enable' => (string) $node->enable == 'true',
                        'img' => (string) $node->img,
                        'min' => (float) $node->min,
                        'max' => (float) $node->max
                    );
                    $results[$data['type']] = $data;
                }
                if (!isset($results[self::PAYMENT_TYPE_TEST])) {
                    $results[self::PAYMENT_TYPE_TEST] = array(
                        'type' => self::PAYMENT_TYPE_TEST,
                        'name' => Mage::helper('payuplpro')->__('Test payment'),
                        'enable' => false,
                        'img' => false,
                        'min' => self::TEST_PAYMENT_MIN,
                        'max' => self::TEST_PAYMENT_MAX,
                    );
                }
                $this->paytypes = $results;
            } catch (Exception $e) {
                $this->paytypes = false;
            }
        }
        return $this->paytypes;
    }

    public function getPaytype() {
        return $this->getQuote()->getPayment()->getAdditionalInformation('payuplpro_paytype');
    }

    protected function getAuth(Mage_Sales_Model_Order_Payment $payment, $camel_case = true) {
        $names = array(
            'pos_id' => $camel_case ? 'posId' : 'pos_id',
            'session_id' => $camel_case ? 'sessionId' : 'session_id',
        );
        $order = Mage::getModel('sales/order')->load($payment->getParentId());
        $store_id = $order->getStoreId();
        $data = array(
            $names['pos_id'] => $this->getConfig()->getPosId($store_id),
            $names['session_id'] => $this->getTransactionSessionId($payment),
            'ts' => time() * rand(1, 10),
            'key1' => $this->getConfig()->getMD5Key1($store_id)
        );
        return array(
            $names['pos_id'] => $data[$names['pos_id']],
            $names['session_id'] => $data[$names['session_id']],
            'sig' => md5($data[$names['pos_id']] . $data[$names['session_id']] . $data['ts'] . $data['key1']),
            'ts' => $data['ts']
        );
    }

    public function refund(Varien_Object $payment, $amount) {
        $_payment = Mage::getModel('sales/order_payment')->load($payment->getEntityId());
        $res = $this->addRefund($_payment, $amount);
        if ($res < 0) {
            Mage::throwException(Mage::helper('payuplpro')->__('Message from Payu.pl') . ': ' . self::$refund_results[$res]);
        }
        return $this;
    }

    public function getRefundSoapClient() {
        if (is_null($this->refund_soap_client)) {
            $this->refund_soap_client = Mage::getModel('payuplpro/soap_refund');
        }
        return $this->refund_soap_client;
    }

    protected function getRefunds(Mage_Sales_Model_Order_Payment $payment) {
        $refund_auth = $this->getRefundAuth($payment);
        $res = $this->getRefundSoapClient()->call('getRefunds', $refund_auth);
        return $res;
    }

    protected function getRefundAuth(Mage_Sales_Model_Order_Payment $payment) {
        return array('RefundAuth' => $this->getAuth($payment));
    }

    public function addRefund(Mage_Sales_Model_Order_Payment $payment, $amount) {
        $refund_auth = $this->getRefundAuth($payment);
        $refund_data = $this->getRefundData($payment, $amount);
        $res = $this->getRefundSoapClient()->call('addRefund', array_merge($refund_auth, $refund_data));
        return $res;
    }

    protected function getRefundData(Mage_Sales_Model_Order_Payment $payment, $amount) {
        $amount = 100 * (float) $amount;
        $refunds = $this->getRefunds($payment);
        $order_id = Mage::getModel('sales/order')->load($payment->getParentId())->getIncrementId();
        return array('RefundData' => array(
                'refundsHash' => $refunds->refsHash,
                'amount' => $amount,
                'desc' => Mage::helper('payuplpro')->__('Order no %s', $order_id),
                'autoData' => true,
                'account' => '',
                'firstName' => '',
                'lastName' => '',
                'address' => '',
                'city' => '',
                'postalCode' => ''
        ));
    }

    public function getTransactionStatus($payment) {
        $store_id = $payment->getOrder()->getStoreId();
        $url = $this->getConfig()->getMethodUrl('Payment/get', $store_id);
        $args = $this->getAuth($payment, false);
        $response = Mage::helper('payuplpro')->sendPost($url, $args);
        $xml = new SimpleXMLElement($response);
        if ((string) $xml->status == 'ERROR') {
            Mage::log('Unable to get Payu transaction for order #' . $payment->getOrder()->getIncrementId() . ': ERROR ' . (string) $xml->error->nr, null, 'payuplpro.log');
            return false;
        } else {
            return ((string) $xml->trans->status);
        }
    }

    public function isCancellationEnabled($payment) {
        $store_id = $payment->getOrder()->getStoreId();
        $order = $payment->getOrder();
        return $this->getConfig()->isActive($store_id) && $this->getConfig()->isCancellationEnabled($store_id) && !$this->isPaymentCompletedOutside($order);
    }

    public function cancelTransaction($payment) {
        if (!$this->getCancelledFlag($payment) && in_array($this->getTransactionStatus($payment), array(self::PAYMENTSTATE_NEW, self::PAYMENTSTATE_INPROGRSSS, self::PAYMENTSTATE_PENDING))) {
            $store_id = $payment->getOrder()->getStoreId();
            $url = $this->getConfig()->getMethodUrl('Payment/cancel', $store_id);
            $args = $this->getAuth($payment, false);
            $response = Mage::helper('payuplpro')->sendPost($url, $args);
            $xml = new SimpleXMLElement($response);
            if ((string) $xml->status == 'ERROR') {
                Mage::log('Unable to cancel Payu transaction for order #' . $payment->getOrder()->getIncrementId() . ': ERROR ' . (string) $xml->error->nr, null, 'payuplpro.log');
            } else {
                $this->setCancelledFlag($payment);
                $this->setState($payment, $this->getTransactionStatus($payment));
            }
        }
    }

    public function confirmTransaction($payment) {
        if (!$this->getConfirmedFlag($payment) && $this->getTransactionStatus($payment) == self::PAYMENTSTATE_PENDING) {
            $store_id = $payment->getOrder()->getStoreId();
            $url = $this->getConfig()->getMethodUrl('Payment/confirm', $store_id);
            $args = $this->getAuth($payment, false);
            $response = Mage::helper('payuplpro')->sendPost($url, $args);
            $xml = new SimpleXMLElement($response);
            if ((string) $xml->status == 'ERROR') {
                Mage::log('Unable to confirm Payu transaction for order #' . $payment->getOrder()->getIncrementId() . ': ERROR ' . (string) $xml->error->nr, null, 'payuplpro.log');
            } else {
                $this->setConfirmedFlag($payment);
                $this->setState($payment, $this->getTransactionStatus($payment));
            }
        }
    }

    public function getConfirmedFlag($payment) {
        return $payment->getAdditionalInformation('payuplpro_is_confirmed');
    }

    protected function setConfirmedFlag($payment) {
        $payment->setAdditionalInformation('payuplpro_is_confirmed', true)->save();
    }

    public function getCancelledFlag($payment) {
        return $payment->getAdditionalInformation('payuplpro_is_cancelled');
    }

    protected function setCancelledFlag($payment) {
        $payment->setAdditionalInformation('payuplpro_is_cancelled', true)->save();
    }

    protected function setState($payment, $state) {
        $payment->setAdditionalInformation('payuplpro_state', $state)->save();
    }

    protected function getOrderTotalInPolishCents($order) {
        if ($order->getBaseCurrencyCode() == self::POLISH_ZLOTY_CODE) {
            $total_pln = $order->getBaseGrandTotal();
        } else if ($order->getOrderCurrencyCode() == self::POLISH_ZLOTY_CODE) {
            $total_pln = $order->getGrandTotal();
        } else {
            $total_pln = Mage::helper('directory')->currencyConvert($order->getBaseGrandTotal(), $order->getBaseCurrencyCode(), self::POLISH_ZLOTY_CODE);
        }
        $rounded_pln = Mage::app()->getStore()->roundPrice($total_pln);
        return $rounded_pln * 100;
    }

    public function getQuoteTotalInPolishZlotys($quote) {
        if ($quote->getBaseCurrencyCode() == self::POLISH_ZLOTY_CODE) {
            $total_pln = $quote->getBaseGrandTotal();
        } else if ($quote->getQuoteCurrencyCode() == self::POLISH_ZLOTY_CODE) {
            $total_pln = $quote->getGrandTotal();
        } else {
            $total_pln = Mage::helper('directory')->currencyConvert($quote->getBaseGrandTotal(), $quote->getBaseCurrencyCode(), self::POLISH_ZLOTY_CODE);
        }
        $rounded_pln = Mage::app()->getStore()->roundPrice($total_pln);
        return $rounded_pln;
    }

    public function getConfigData($field, $storeId = null) {
        if ($field == 'active') {
            $is_module_output_enabled = Mage::helper('core')->isModuleOutputEnabled('Orba_Payuplpro');
            if (!$is_module_output_enabled) {
                return false;
            }
        }
        return parent::getConfigData($field, $storeId);
    }

    public function paidOutside($payment) {
        try {
            $session_id = $this->getTransactionSessionId($payment);
            $t = $payment->setTransactionId($session_id);
            $t->setPreparedMessage('Transakcja zakończona poza Payu.')
                    ->registerCaptureNotification($payment->getAmountOrdered())
                    ->setIsTransactionApproved(true)
                    ->setIsTransactionClosed(true)
                    ->setAdditionalInformation('payuplpro_is_completed', true)
                    ->setAdditionalInformation('payuplpro_is_completed_outside', true)
                    ->save();

            $this->cancelTransaction($payment);
            // notify customer
            $state = Mage_Sales_Model_Order::STATE_PROCESSING;
            $comment = $payment->getOrder()->setState($state, true, 'Transakcja zakończona poza Payu.', true)
                    ->sendOrderUpdateEmail(true, 'Transakcja zakończona poza Payu.')
                    ->save();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function isPaymentCompletedOutside($order) {
        if ($order) {
            $data = $order->getPayment()->getAdditionalInformation();
            $is_completed = isset($data['payuplpro_is_completed_outside']) ? $data['payuplpro_is_completed_outside'] : false;
            return $is_completed;
        }
        return false;
    }

    public function unblock($payment) {
        try {
            $session_id = $this->getTransactionSessionId($payment);
            $t = $payment->setTransactionId($session_id);
            $t->setPreparedMessage('Transakcja zakończona pomyślnie. [' . Orba_Payuplpro_Model_Payment::PAYMENTSTATE_COMPLETED . ']')
                    ->registerPayuplproCaptureNotification($payment->getAmountOrdered())
                    ->setIsTransactionApproved(true)
                    ->setIsTransactionClosed(true)
                    ->setAdditionalInformation('payuplpro_is_completed', true)
                    ->save();
            // notify customer
            $state = Mage_Sales_Model_Order::STATE_PROCESSING;
            $order = $payment->getOrder();
            $store_id = $order->getStoreId();
            $comment = $order->setState($state, $this->getConfig()->getDefaultStatus($state, $store_id), 'Komunikat z Payu.pl: Transakcja zakończona pomyślnie.', true)
                    ->sendOrderUpdateEmail(true, 'Komunikat z Payu.pl: Transakcja zakończona pomyślnie.')
                    ->save();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

}
