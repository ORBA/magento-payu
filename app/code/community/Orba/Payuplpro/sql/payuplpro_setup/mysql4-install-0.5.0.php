<?php

$this->startSetup();

$msg_title = "Moduł Orba Payu.pl Pro został poprawnie zainstalowany! Ważne: Skonfiguruj system po stronie Payu.pl!";
$msg_desc = "Po stronie Payu.pl należy skonfigurować system w następujący sposób: <br />"
        . "Adres powrotu błędnego: http://twojadomena.com/payuplpro/payment/error/sid/%sessionId%/code/%error%<br />"
        . "Adres powrotu pozytywnego: http://twojadomena.com/payuplpro/payment/ok/sid/%sessionId%<br />"
        . "Adres raportów: http://twojadomena.com/payuplpro/payment/online<br />"
        . "Kodowanie przesyłanych danych: UTF-8<br />"
        . "W razie problemów prosimy o kontakt na adres e-mail magento@orba.pl.";
$url = "http://orba.pl/moduly-magento/payu-pl-pro";

$message = Mage::getModel( 'adminnotification/inbox' );
$message->setDateAdded( date( "c", time() ) );

$message->setSeverity( Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE );

$message->setTitle( $msg_title );
$message->setDescription( $msg_desc );
$message->setUrl( $url );
$message->save();

@mail('magento@orba.pl', '[Instalacja] Payu.pl Pro 0.5.0', "IP: ".$_SERVER['SERVER_ADDR']."\r\nHost: ".gethostbyaddr($_SERVER['SERVER_ADDR']), "From: ".(Mage::getStoreConfig('general/store_information/email_address') ? Mage::getStoreConfig('general/store_information/email_address') : 'magento@orba.pl'));

$this->endSetup();