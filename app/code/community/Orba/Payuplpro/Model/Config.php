<?php

class Orba_Payuplpro_Model_Config {
    
    public function isActive($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/active', $store_id);
    }
    
    public function getDescription($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/description', $store_id);
    }
    
    public function getGatewayUrl($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/gateway_url', $store_id);
    }
    
    public function getGatewayType($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/gateway_type', $store_id);
    }
    
    public function getPosId($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/pos_id', $store_id);
    }
    
    public function getPosAuthKey($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/pos_auth_key', $store_id);
    }
    
    public function getMD5Key1($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/md5key1', $store_id);
    }
    
    public function getMD5Key2($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/md5key2', $store_id);
    }
    
    public function getEncoding($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/encoding', $store_id);
    }
    
    public function isCancellationEnabled($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/cancel_enabled', $store_id);
    }
    
    public function shouldShowLinkInEmail($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/link_in_email', $store_id);
    }
    
    public function getMethodUrl($name, $store_id = null) {
        return $this->getGatewayUrl($store_id).'/'.$this->getEncoding($store_id).'/'.$name;
    }
    
    public function getWebApiUrl($store_id = null) {
        return $this->getGatewayUrl($store_id).'/webapi';
    }
    
    public function getDefaultStatus($state, $store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/status_' . $state, $store_id);
    }
    
    public function getCreateOrderInvoice($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/create_order_invoice', $store_id);
    }

    public function isProcessingOldPaidTransactionsEnabled($store_id = null) {
        return Mage::getStoreConfig('payment/payuplpro/process_old_paid_transactions', $store_id);
    }
}
  