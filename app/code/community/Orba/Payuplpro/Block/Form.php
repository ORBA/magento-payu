<?php

class Orba_Payuplpro_Block_Form extends Mage_Payment_Block_Form
{
    /**
     * Payment method code
     * @var string
     */
    protected $_methodCode = 'payuplpro';

    
    /**
     * Set template and redirect message
     */
    protected function _construct()
    {
        $this->setTemplate('payuplpro/form.phtml');
        $this->setMethodTitle('');
        $this->setMethodLabelAfterHtml('<img src="'.Mage::getDesign()->getSkinUrl('images/payuplpro/logo.png').'" alt="Payu.pl"/> ' . $this->getConfig()->getDescription());
        
        return parent::_construct();
    }
    
    public function getConfig() {
        return Mage::getSingleton('payuplpro/config');
    }

    /**
     * Payment method code getter
     * @return string
     */
    public function getMethodCode()
    {
        return $this->_methodCode;
    }
    
    public function choosePaymentMethodAfterCheckout() {
        $gateway_type = $this->getConfig()->getGatewayType();
        return $gateway_type == Orba_Payuplpro_Model_Gatewaytype::PAYUPLPRO_GATEWAYTYPE_AFTER_CHECKOUT;
    }
    
    public function getPaymentTypes() {
        $_payment = Mage::getSingleton('payuplpro/payment');
        $paytypes = $_payment->getPaytypes();
        $results = array();
        if ($paytypes) {
            $quote = $_payment->getQuote();
            $total = $_payment->getQuoteTotalInPolishZlotys($quote);
            $results = array(
                'card' => array(
                    'label' => Mage::helper('payuplpro')->__('Credit/Debit Card'),
                    'items' => array()
                ),
                'bank' => array(
                    'label' => Mage::helper('payuplpro')->__('E-transfer'),
                    'items' => array()
                ),
                'others' => array(
                    'label' => Mage::helper('payuplpro')->__('Others'),
                    'items' => array()
                )
            );
            foreach ($paytypes as $paytype) {
                if ($paytype['enable'] && $paytype['min'] <= $total && $paytype['max'] >= $total) {
                    $type = $paytype['type'];
                    switch ($type) {
                        case Orba_Payuplpro_Model_Payment::PAYMENT_TYPE_CARD:
                            $container = 'card';
                            break;
                        case Orba_Payuplpro_Model_Payment::PAYMENT_TYPE_TRADITIONAL:
                        case Orba_Payuplpro_Model_Payment::PAYMENT_TYPE_PAYU:
                        case Orba_Payuplpro_Model_Payment::PAYMENT_TYPE_TEST:
                        case Orba_Payuplpro_Model_Payment::PAYMENT_TYPE_INSTALMENTS:
                            $container = 'others';
                            break;
                        default:
                            $container = 'bank';
                    }
                    $results[$container]['items'][] = array(
                        'type' => $type,
                        'img' => $paytype['img'],
                        'name' => $paytype['name']
                    );
                }
            }
        }
        return $results;
    }
    
    public function getCheckedPaymentType() {
        return Mage::getSingleton('payuplpro/payment')->getPaytype();
    }

}