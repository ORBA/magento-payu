<?php
class Orba_Payuplpro_Helper_Data extends Mage_Core_Helper_Abstract {
    
    public function getRepeatPaymentUrl($order) {
        $hash = $order->getPayment()->getAdditionalInformation('payuplpro_hash');
        if (!$hash) {
            $hash = $order->getPayment()->getAdditionalInformation('payuplpro_customer_sid');
        }
        if ($hash) {
            return Mage::getUrl('payuplpro/payment/repeat', array('id' => $order->getId(), 'hash' => $hash, '_store' => Mage::app()->getStore()->getCode()));
        }
        return false;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public function getPayPaymentUrl($order) {
        $url = Mage::getUrl('payuplpro/payment/pay', array(
            'id' => $order->getId(),
            'hash' => $order->getPayment()->getAdditionalInformation('payuplpro_hash'),
            '_store' => $order->getStore()->getCode()));
        return $url;
    }
    
    public function sendPost($url, $args = array()) {
        return $this->sendRequest($url, $args, Zend_Http_Client::POST);
    }
    
    public function sendRequest($url, $args = array(), $method = Zend_Http_Client::GET) {
        $client = new Zend_Http_Client($url);
        $adapter = new Zend_Http_Client_Adapter_Curl();
        $adapter->setConfig(array(
            'curloptions' => array(
                CURLOPT_SSLVERSION => 1,
                // our experience shows that Payu doesn't need this option
                // older versions of CURL don't understand it
                // hence it's commented
                // 
                // CURLOPT_SSL_CIPHER_LIST => 'TLSv1',
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => false
            )
        ));
        $client->setAdapter($adapter);
        if (!empty($args)) {
            foreach ($args as $name => $value) {
                $client->setParameterPost($name, $value);
            }
        }
        try{
            $response = $client->request($method);
            if ($response->isSuccessful()) {
                $headers = $response->getHeaders();
                $gzipped = isset($headers['Content-encoding']) && $headers['Content-encoding'] === 'gzip';
                return $gzipped ? $this->decodeGzip($response->getRawBody()) : $response->getRawBody();
            } else {
                Mage::log('Unable to send request to Payu: '.$response->getStatus(), null, 'payuplpro.log');
            }
        } catch (Exception $e) {
            Mage::log('Unable to send request to Payu: '.$e->getMessage(), null, 'payuplpro.log');
        }
        return false;
    }
    
    public function decodeGzip($data) {
        return Zend_Http_Response::decodeGzip($data);
    }
    
    public function getHash() {
        return md5('payuplpro'.(time() * rand(1, 123)));
    }
    
}