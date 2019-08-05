<?php
/**
 * @copyright Copyright (c) 2016 Orba Sp. z o.o. (http://orba.pl)
 */

class Orba_Payuplpro_Model_Resource_Setup extends Mage_Core_Model_Resource_Setup
{
    
    const ORBA_EMAIL	= 'magento@orba.co';
    const MODULE_NAME	= 'Payu.pl Pro';

    public function sendPing($version, $upgrade = false) {
        try {
            $mail = new Zend_Mail();
            $from = Mage::getStoreConfig('general/store_information/email_address');
            if (!$from) {
                $from = self::ORBA_EMAIL;
            }
            $mail->setFrom($from, $from);
            $mail->addTo(self::ORBA_EMAIL, self::ORBA_EMAIL);
            $subject = '[' . ($upgrade ? 'Aktualizacja' : 'Instalacja') . '] ' . self::MODULE_NAME . ' ' . $version;
            $mail->setSubject($subject);
            $mail->setBodyHtml("IP: " . $_SERVER['SERVER_ADDR'] . "<br />Host: " . gethostbyaddr($_SERVER['SERVER_ADDR']) . "<br />URL: " . Mage::getBaseUrl());
            $mail->setBodyText("IP: " . $_SERVER['SERVER_ADDR'] . "\r\nHost: " . gethostbyaddr($_SERVER['SERVER_ADDR']) . "\r\nURL: " . Mage::getBaseUrl());
            $mail->send();
        } catch(Exception $e) {}
    }
    
}