<?php
class Orba_Payuplpro_Model_Soap extends Mage_Core_Model_Abstract {
    
    protected $ns = '';
    protected $client = null;
    
    protected function getConfig() {
        return Mage::getModel('payuplpro/config');  
    }
    
    protected function _construct() {
        $this->client = new SoapClient($this->getConfig()->getWebApiUrl().'/'.$this->ns.'?wsdl');
    } 
    
    public function call($method, $args = array()) {
        return $this->client->__soapCall($method, $args);
    }
    
}