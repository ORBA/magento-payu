<?php

class Orba_Payuplpro_Model_Status_Processing extends Mage_Adminhtml_Model_System_Config_Source_Order_Status {
    
    protected $_stateStatuses = array(
        Mage_Sales_Model_Order::STATE_PROCESSING,
    );
    
}